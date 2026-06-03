import 'dart:async';

import 'package:flutter/material.dart';

import '../models/conteo_item.dart';
import '../models/producto.dart';
import '../models/toma.dart';
import '../services/api_client.dart';
import '../services/local_draft_store.dart';
import 'scanner_screen.dart';

class ConteoScreen extends StatefulWidget {
  const ConteoScreen({
    super.key,
    required this.api,
    required this.toma,
    required this.conteoId,
  });

  final ApiClient api;
  final Toma toma;
  final int conteoId;

  @override
  State<ConteoScreen> createState() => _ConteoScreenState();
}

class _ConteoScreenState extends State<ConteoScreen> {
  static const Duration _serverAutosaveInterval = Duration(minutes: 3);
  static const Duration _quantityFocusDuration = Duration(seconds: 5);

  final _buscar = TextEditingController();
  final List<ConteoItem> _items = [];
  final Map<int, TextEditingController> _cantidadControllers = {};
  final Map<int, FocusNode> _cantidadFocusNodes = {};
  final Map<int, ConteoItem> _pendingUpsert = {};
  final Set<int> _pendingRemove = {};
  Timer? _searchDebounce;
  Timer? _autosaveDebounce;
  Timer? _autosaveTimer;
  Timer? _quantityFocusTimer;
  LocalDraftStore? _store;
  bool _loading = true;
  bool _saving = false;
  bool _searching = false;
  bool _autosaving = false;
  bool _pendingSync = false;
  int _conteoVersion = 0;
  int _searchRequestId = 0;
  String _syncStatus = 'Guardado local';
  List<Producto> _resultados = [];

  bool get _hasPendingServerChanges =>
      _pendingUpsert.isNotEmpty || _pendingRemove.isNotEmpty;

  @override
  void initState() {
    super.initState();
    _buscar.addListener(() {
      if (mounted) setState(() {});
    });
    _init();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _autosaveDebounce?.cancel();
    _autosaveTimer?.cancel();
    _quantityFocusTimer?.cancel();
    _store?.close();
    _buscar.dispose();
    for (final controller in _cantidadControllers.values) {
      controller.dispose();
    }
    for (final focusNode in _cantidadFocusNodes.values) {
      focusNode.dispose();
    }
    super.dispose();
  }

  Future<void> _init() async {
    _store = await LocalDraftStore.open();
    final localItems = await _store!.load(widget.conteoId);
    try {
      final remoteSnapshot = await widget.api.fetchDetalle(widget.conteoId);
      _conteoVersion = remoteSnapshot.version;
      _items
        ..clear()
        ..addAll(localItems.isNotEmpty ? localItems : remoteSnapshot.items);
    } catch (_) {
      _items
        ..clear()
        ..addAll(localItems);
    }
    _syncQuantityFields();
    if (mounted) {
      setState(() => _loading = false);
    }
    _autosaveTimer = Timer.periodic(_serverAutosaveInterval, (_) {
      if (_pendingSync && !_autosaving && _items.isNotEmpty) {
        _sincronizarBorrador(silent: true);
      }
    });
  }

  Future<void> _guardarLocalYProgramarSync() async {
    await _store?.save(widget.conteoId, _items);
    if (!mounted) return;
    setState(() {
      _pendingSync = true;
      _syncStatus = 'Pendiente de sincronizar';
    });
    _autosaveDebounce?.cancel();
    _autosaveDebounce = Timer(_serverAutosaveInterval, () {
      _sincronizarBorrador(silent: true);
    });
  }

  Future<void> _sincronizarBorrador({bool silent = false}) async {
    if (_autosaving) return;
    if (!_hasPendingServerChanges && (silent || _items.isEmpty)) return;
    if (mounted) {
      setState(() {
        _autosaving = true;
        _syncStatus = silent ? 'Autoguardando...' : 'Guardando...';
      });
    }
    try {
      if (_hasPendingServerChanges) {
        final upsertSnapshot = _pendingUpsert.values.map(_copyItem).toList();
        final removeSnapshot = _pendingRemove.toList();
        _conteoVersion = await widget.api.guardarCambios(
          conteoId: widget.conteoId,
          upsert: upsertSnapshot,
          remove: removeSnapshot,
          conteoVersion: _conteoVersion,
        );
        _confirmarCambiosSincronizados(upsertSnapshot, removeSnapshot);
      } else {
        _conteoVersion = await widget.api
            .guardarBorrador(widget.conteoId, _items, _conteoVersion);
      }
      await _store?.save(widget.conteoId, _items);
      if (!mounted) return;
      final now = TimeOfDay.now().format(context);
      setState(() {
        _pendingSync = _hasPendingServerChanges;
        _syncStatus =
            _pendingSync ? 'Pendiente de sincronizar' : 'Autoguardado $now';
      });
      if (!silent) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Borrador guardado')),
        );
      }
    } catch (error) {
      await _store?.save(widget.conteoId, _items);
      if (!mounted) return;
      setState(() {
        _pendingSync = true;
        _syncStatus = 'Guardado local - sin conexion';
      });
      if (!silent) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Guardado local. Sincronice luego. $error')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _autosaving = false);
      }
    }
  }

  ConteoItem _copyItem(ConteoItem item) {
    return ConteoItem(
      productoId: item.productoId,
      codigo: item.codigo,
      descripcion: item.descripcion,
      cantidad: item.cantidad,
    );
  }

  void _markItemChanged(ConteoItem item) {
    _pendingRemove.remove(item.productoId);
    _pendingUpsert[item.productoId] = item;
  }

  void _markItemRemoved(int productoId) {
    _pendingUpsert.remove(productoId);
    _pendingRemove.add(productoId);
  }

  void _confirmarCambiosSincronizados(
      List<ConteoItem> upsertSnapshot, List<int> removeSnapshot) {
    for (final item in upsertSnapshot) {
      final current = _pendingUpsert[item.productoId];
      if (current != null &&
          current.codigo == item.codigo &&
          current.descripcion == item.descripcion &&
          current.cantidad == item.cantidad) {
        _pendingUpsert.remove(item.productoId);
      }
    }
    for (final productoId in removeSnapshot) {
      _pendingRemove.remove(productoId);
    }
  }

  void _onBuscarChanged(String value) {
    _searchDebounce?.cancel();
    final q = value.trim();
    if (q.length < 3) {
      setState(() {
        _resultados = [];
        _searching = false;
      });
      return;
    }
    setState(() => _searching = true);
    _searchDebounce = Timer(const Duration(milliseconds: 420), () {
      _buscarProductos(q);
    });
  }

  void _syncQuantityFields() {
    final activeIds = _items.map((item) => item.productoId).toSet();
    for (final id in _cantidadControllers.keys.toList()) {
      if (!activeIds.contains(id)) {
        _cantidadControllers.remove(id)?.dispose();
        _cantidadFocusNodes.remove(id)?.dispose();
      }
    }

    for (final item in _items) {
      final controller = _cantidadControllers.putIfAbsent(
        item.productoId,
        () => TextEditingController(),
      );
      final focusNode =
          _cantidadFocusNodes.putIfAbsent(item.productoId, FocusNode.new);
      final value = _formatCantidad(item.cantidad);
      if (controller.text != value && !focusNode.hasFocus) {
        controller.text = value;
      }
    }
  }

  TextEditingController _cantidadControllerFor(ConteoItem item) {
    return _cantidadControllers.putIfAbsent(
      item.productoId,
      () => TextEditingController(text: _formatCantidad(item.cantidad)),
    );
  }

  FocusNode _cantidadFocusFor(ConteoItem item) {
    return _cantidadFocusNodes.putIfAbsent(item.productoId, FocusNode.new);
  }

  void _focusCantidad(int productoId) {
    _quantityFocusTimer?.cancel();
    final startedAt = DateTime.now();

    void requestFocus() {
      if (!mounted) return;
      final focusNode = _cantidadFocusNodes[productoId];
      final controller = _cantidadControllers[productoId];
      if (focusNode == null || controller == null) return;
      focusNode.requestFocus();
      controller.selection =
          TextSelection(baseOffset: 0, extentOffset: controller.text.length);
    }

    WidgetsBinding.instance.addPostFrameCallback((_) => requestFocus());
    _quantityFocusTimer =
        Timer.periodic(const Duration(milliseconds: 260), (timer) {
      if (DateTime.now().difference(startedAt) > _quantityFocusDuration) {
        timer.cancel();
        if (_quantityFocusTimer == timer) {
          _quantityFocusTimer = null;
        }
        return;
      }
      requestFocus();
    });
  }

  Future<void> _buscarProductos([String? term]) async {
    final q = (term ?? _buscar.text).trim();
    if (q.length < 3) {
      setState(() {
        _resultados = [];
        _searching = false;
      });
      return;
    }
    final requestId = ++_searchRequestId;
    setState(() => _searching = true);
    try {
      final productos = await widget.api.buscarProductos(q);
      if (mounted) {
        if (requestId == _searchRequestId &&
            ((term != null && term == q) || _buscar.text.trim() == q)) {
          setState(() => _resultados = productos);
        }
      }
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('$error')));
    } finally {
      if (mounted && requestId == _searchRequestId) {
        setState(() => _searching = false);
      }
    }
  }

  Future<void> _scan() async {
    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const ScannerScreen()),
    );
    if (code == null || code.isEmpty) return;
    _buscar.text = code;
    await _buscarProductos(code);
  }

  Future<void> _addProducto(Producto producto) async {
    final existingIndex =
        _items.indexWhere((item) => item.productoId == producto.id);
    if (existingIndex >= 0) {
      setState(() {
        final item = _items.removeAt(existingIndex);
        _items.insert(0, item);
        _markItemChanged(item);
        _buscar.clear();
        _resultados = [];
        _syncQuantityFields();
      });
      await _guardarLocalYProgramarSync();
      _focusCantidad(producto.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text(
                'Producto ya registrado. Actualice cantidad: ${producto.codigo}')),
      );
      return;
    }

    setState(() {
      final item = ConteoItem.fromProducto(producto, 0);
      _items.insert(0, item);
      _markItemChanged(item);
      _buscar.clear();
      _resultados = [];
      _syncQuantityFields();
    });
    await _guardarLocalYProgramarSync();
    _focusCantidad(producto.id);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Producto agregado: ${producto.codigo}')),
    );
  }

  String _formatCantidad(double value) {
    return value.truncateToDouble() == value
        ? value.toStringAsFixed(0)
        : value.toStringAsFixed(2);
  }

  Future<void> _eliminarItem(ConteoItem item) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Eliminar producto'),
        content: Text('Desea eliminar ${item.codigo} del conteo?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Eliminar'),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    setState(() {
      _markItemRemoved(item.productoId);
      _items.removeWhere((current) => current.productoId == item.productoId);
      _syncQuantityFields();
    });
    await _guardarLocalYProgramarSync();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Producto eliminado: ${item.codigo}')),
    );
  }

  Future<void> _actualizarCantidadItem(ConteoItem item, String value) async {
    final cantidad = double.tryParse(value.replaceAll(',', '.')) ?? 0;
    setState(() {
      item.cantidad = cantidad;
      _markItemChanged(item);
    });
    _quantityFocusTimer?.cancel();
    await _guardarLocalYProgramarSync();
  }

  double get _totalUnidades {
    return _items.fold<double>(0, (total, item) => total + item.cantidad);
  }

  Future<void> _guardar() async {
    if (_items.isEmpty && !_hasPendingServerChanges) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Agregue productos antes de guardar')),
      );
      return;
    }
    setState(() => _saving = true);
    await _sincronizarBorrador();
    if (mounted) {
      setState(() => _saving = false);
    }
  }

  Future<void> _finalizar() async {
    if (_items.isEmpty) return;
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Finalizar conteo'),
        content:
            const Text('Despues de finalizar no podra editar este conteo.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancelar')),
          FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Finalizar')),
        ],
      ),
    );
    if (confirm != true) return;

    setState(() => _saving = true);
    try {
      _autosaveDebounce?.cancel();
      await _sincronizarBorrador(silent: true);
      await widget.api.finalizarConteo(widget.conteoId, _items, _conteoVersion);
      await _store?.clear(widget.conteoId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Conteo finalizado')),
      );
      Navigator.pop(context);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('$error')));
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        centerTitle: true,
        title: Column(
          children: [
            Text(
              'Iter',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: const Color(0xFF004080),
                    fontWeight: FontWeight.w900,
                    height: 1,
                  ),
            ),
            const Text('Conteo'),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: Color(0xFFC8DBF2))),
          ),
          child: OutlinedButton.icon(
            onPressed: _saving ? null : _guardar,
            icon: const Icon(Icons.save_outlined, size: 16),
            label: const Text('Guardar borrador'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(38),
              textStyle: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(10, 10, 10, 18),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    widget.toma.numeroToma,
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium
                                        ?.copyWith(
                                          color: const Color(0xFF004080),
                                          fontWeight: FontWeight.w900,
                                        ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    widget.toma.nombreToma,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFF101828),
                                      height: 1.25,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (_autosaving)
                              const SizedBox(
                                width: 18,
                                height: 18,
                                child:
                                    CircularProgressIndicator(strokeWidth: 2),
                              )
                            else
                              Icon(
                                _pendingSync
                                    ? Icons.cloud_off_outlined
                                    : Icons.cloud_done_outlined,
                                size: 20,
                                color: _pendingSync
                                    ? const Color(0xFF946200)
                                    : const Color(0xFF087443),
                              ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 6,
                          runSpacing: 6,
                          children: [
                            if (widget.toma.agencia != null &&
                                widget.toma.agencia!.isNotEmpty)
                              _InfoPill(
                                icon: Icons.storefront_outlined,
                                text: widget.toma.agencia!,
                              ),
                            _InfoPill(
                              icon: _pendingSync
                                  ? Icons.sync_problem_outlined
                                  : Icons.verified_outlined,
                              text: _syncStatus,
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        ElevatedButton.icon(
                          onPressed: _saving ? null : _finalizar,
                          icon:
                              const Icon(Icons.check_circle_outline, size: 16),
                          label: const Text('Finalizar conteo'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF198754),
                            foregroundColor: Colors.white,
                            minimumSize: const Size.fromHeight(38),
                            textStyle: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Buscar producto',
                          style:
                              Theme.of(context).textTheme.labelLarge?.copyWith(
                                    color: const Color(0xFF004080),
                                    fontWeight: FontWeight.w900,
                                  ),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _buscar,
                                textInputAction: TextInputAction.search,
                                decoration: InputDecoration(
                                  hintText: 'Codigo o descripcion',
                                  prefixIcon: const Icon(Icons.search),
                                  suffixIcon: _searching
                                      ? const Padding(
                                          padding: EdgeInsets.all(14),
                                          child: SizedBox(
                                            width: 16,
                                            height: 16,
                                            child: CircularProgressIndicator(
                                                strokeWidth: 2),
                                          ),
                                        )
                                      : (_buscar.text.isNotEmpty
                                          ? IconButton(
                                              onPressed: () {
                                                _searchDebounce?.cancel();
                                                setState(() {
                                                  _buscar.clear();
                                                  _resultados = [];
                                                  _searching = false;
                                                });
                                              },
                                              icon: const Icon(Icons.close),
                                            )
                                          : null),
                                ),
                                onChanged: _onBuscarChanged,
                                onSubmitted: _buscarProductos,
                              ),
                            ),
                            const SizedBox(width: 10),
                            IconButton.filled(
                              onPressed: _scan,
                              icon: const Icon(Icons.qr_code_scanner),
                            ),
                          ],
                        ),
                        if (_resultados.isNotEmpty) ...[
                          const SizedBox(height: 12),
                          Text(
                            'Resultados (${_resultados.length})',
                            style: Theme.of(context)
                                .textTheme
                                .labelLarge
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          ..._resultados.map(
                            _buildSearchResult,
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  'Productos contados (${_items.length})',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                Text(
                  'Unidades: ${_formatCantidad(_totalUnidades)}',
                  style: Theme.of(context)
                      .textTheme
                      .bodyMedium
                      ?.copyWith(color: const Color(0xFF4E6380)),
                ),
                const SizedBox(height: 8),
                if (_items.isEmpty)
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(18),
                      child: Text('Aun no hay productos agregados.'),
                    ),
                  )
                else
                  ..._items.map(_buildCountItemCard),
              ],
            ),
    );
  }

  Widget _buildSearchResult(Producto producto) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: () => _addProducto(producto),
        child: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: const Color(0xFFF8FBFF),
            border: Border.all(color: const Color(0xFFD6E4F5)),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      producto.codigo,
                      style: const TextStyle(
                        color: Color(0xFF004080),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      producto.descripcion,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF26364A),
                        height: 1.25,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.add_circle_outline, color: Color(0xFF1F5D9F)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCountItemCard(ConteoItem item) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(9),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              IconButton(
                onPressed: () => _eliminarItem(item),
                icon: const Icon(Icons.delete_outline),
                color: Theme.of(context).colorScheme.error,
                tooltip: 'Eliminar',
                style: IconButton.styleFrom(
                  side: BorderSide(color: Theme.of(context).colorScheme.error),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEAF2FF),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        item.codigo,
                        style: const TextStyle(
                          color: Color(0xFF004080),
                          fontWeight: FontWeight.w900,
                          fontSize: 12,
                        ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      item.descripcion,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                        height: 1.22,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              SizedBox(
                width: 76,
                child: TextField(
                  controller: _cantidadControllerFor(item),
                  focusNode: _cantidadFocusFor(item),
                  keyboardType:
                      const TextInputType.numberWithOptions(decimal: true),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Color(0xFF101828),
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                  decoration: const InputDecoration(
                    contentPadding:
                        EdgeInsets.symmetric(horizontal: 6, vertical: 10),
                  ),
                  onChanged: (value) {
                    _actualizarCantidadItem(item, value);
                  },
                  onSubmitted: (_) => FocusScope.of(context).unfocus(),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _InfoPill extends StatelessWidget {
  const _InfoPill({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF2FF),
        border: Border.all(color: const Color(0xFFC8DBF2)),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: const Color(0xFF1F5D9F)),
          const SizedBox(width: 5),
          Text(
            text,
            style: const TextStyle(
              color: Color(0xFF004080),
              fontWeight: FontWeight.w800,
              fontSize: 11.5,
            ),
          ),
        ],
      ),
    );
  }
}
