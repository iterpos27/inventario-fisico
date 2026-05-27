import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

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

  final _buscar = TextEditingController();
  final List<ConteoItem> _items = [];
  late LocalDraftStore _store;
  Timer? _searchDebounce;
  Timer? _autosaveDebounce;
  Timer? _autosaveTimer;
  bool _loading = true;
  bool _saving = false;
  bool _searching = false;
  bool _autosaving = false;
  bool _pendingSync = false;
  String _syncStatus = 'Guardado local';
  List<Producto> _resultados = [];

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
    _buscar.dispose();
    super.dispose();
  }

  Future<void> _init() async {
    final prefs = await SharedPreferences.getInstance();
    _store = LocalDraftStore(prefs);
    final localItems = _store.load(widget.conteoId);
    try {
      final remoteItems = await widget.api.fetchDetalle(widget.conteoId);
      _items
        ..clear()
        ..addAll(localItems.isNotEmpty ? localItems : remoteItems);
    } catch (_) {
      _items
        ..clear()
        ..addAll(localItems);
    }
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
    await _store.save(widget.conteoId, _items);
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
    if (_items.isEmpty || _autosaving) return;
    if (mounted) {
      setState(() {
        _autosaving = true;
        _syncStatus = 'Autoguardando...';
      });
    }
    try {
      await widget.api.guardarBorrador(widget.conteoId, _items);
      await _store.save(widget.conteoId, _items);
      if (!mounted) return;
      final now = TimeOfDay.now().format(context);
      setState(() {
        _pendingSync = false;
        _syncStatus = 'Autoguardado $now';
      });
      if (!silent) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Borrador guardado')),
        );
      }
    } catch (error) {
      await _store.save(widget.conteoId, _items);
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

  void _onBuscarChanged(String value) {
    _searchDebounce?.cancel();
    final q = value.trim();
    if (q.length < 2) {
      setState(() {
        _resultados = [];
        _searching = false;
      });
      return;
    }
    setState(() => _searching = true);
    _searchDebounce = Timer(const Duration(milliseconds: 280), () {
      _buscarProductos(q);
    });
  }

  Future<void> _buscarProductos([String? term]) async {
    final q = (term ?? _buscar.text).trim();
    if (q.length < 2) {
      setState(() {
        _resultados = [];
        _searching = false;
      });
      return;
    }
    setState(() => _searching = true);
    try {
      final productos = await widget.api.buscarProductos(q);
      if (mounted) {
        if ((term != null && term == q) || _buscar.text.trim() == q) {
          setState(() => _resultados = productos);
        }
      }
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
    } finally {
      if (mounted) {
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
    final existingIndex = _items.indexWhere((item) => item.productoId == producto.id);
    if (existingIndex >= 0) {
      final existente = _items[existingIndex];
      final cambiar = await _confirmarCambioCantidad(existente);
      if (cambiar != true) return;

      final cantidad = await _pedirCantidad(producto, initialValue: _formatCantidad(existente.cantidad));
      if (cantidad == null) return;

      setState(() {
        final item = _items.removeAt(existingIndex);
        item.cantidad = cantidad;
        _items.insert(0, item);
        _buscar.clear();
        _resultados = [];
      });
      await _guardarLocalYProgramarSync();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Cantidad actualizada: ${producto.codigo}')),
      );
      return;
    }

    final cantidad = await _pedirCantidad(producto);
    if (cantidad == null) return;

    setState(() {
      _items.insert(0, ConteoItem.fromProducto(producto, cantidad));
      _buscar.clear();
      _resultados = [];
    });
    await _guardarLocalYProgramarSync();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Producto agregado: ${producto.codigo}')),
    );
  }

  Future<bool?> _confirmarCambioCantidad(ConteoItem item) {
    return showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Producto ya registrado'),
        content: Text('El codigo ${item.codigo} ya esta en el conteo con cantidad ${_formatCantidad(item.cantidad)}. Desea cambiar la cantidad?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Cambiar'),
          ),
        ],
      ),
    );
  }

  Future<double?> _pedirCantidad(Producto producto, {String initialValue = ''}) async {
    final controller = TextEditingController(text: initialValue);
    return showDialog<double>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cantidad'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              producto.codigo,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(producto.descripcion),
            const SizedBox(height: 14),
            TextField(
              controller: controller,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                labelText: 'Cantidad',
                prefixIcon: Icon(Icons.tag),
              ),
              onSubmitted: (_) {
                Navigator.pop(context, double.tryParse(controller.text.replaceAll(',', '.')) ?? 0);
              },
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, double.tryParse(controller.text.replaceAll(',', '.')) ?? 0),
            child: const Text('Aceptar'),
          ),
        ],
      ),
    );
  }

  String _formatCantidad(double value) {
    return value.truncateToDouble() == value ? value.toStringAsFixed(0) : value.toStringAsFixed(2);
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
      _items.removeWhere((current) => current.productoId == item.productoId);
    });
    await _guardarLocalYProgramarSync();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Producto eliminado: ${item.codigo}')),
    );
  }

  Future<void> _editarCantidadItem(ConteoItem item) async {
    final cantidad = await _pedirCantidad(
      Producto(id: item.productoId, codigo: item.codigo, descripcion: item.descripcion),
      initialValue: _formatCantidad(item.cantidad),
    );
    if (cantidad == null) return;
    setState(() {
      item.cantidad = cantidad;
    });
    await _guardarLocalYProgramarSync();
  }

  double get _totalUnidades {
    return _items.fold<double>(0, (total, item) => total + item.cantidad);
  }

  Future<void> _guardar() async {
    if (_items.isEmpty) {
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
        content: const Text('Despues de finalizar no podra editar este conteo.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Finalizar')),
        ],
      ),
    );
    if (confirm != true) return;

    setState(() => _saving = true);
    try {
      _autosaveDebounce?.cancel();
      await _sincronizarBorrador(silent: true);
      await widget.api.finalizarConteo(widget.conteoId, _items);
      await _store.clear(widget.conteoId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Conteo finalizado')),
      );
      Navigator.pop(context);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Conteo ${widget.toma.numeroToma}')),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _saving ? null : _guardar,
                  icon: const Icon(Icons.save_outlined),
                  label: const Text('Borrador'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _saving ? null : _finalizar,
                  icon: const Icon(Icons.check_circle_outline),
                  label: const Text('Finalizar'),
                ),
              ),
            ],
          ),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.toma.nombreToma,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _buscar,
                                textInputAction: TextInputAction.search,
                                decoration: InputDecoration(
                                  labelText: 'Codigo o descripcion',
                                  prefixIcon: const Icon(Icons.search),
                                  suffixIcon: _searching
                                      ? const Padding(
                                          padding: EdgeInsets.all(14),
                                          child: SizedBox(
                                            width: 16,
                                            height: 16,
                                            child: CircularProgressIndicator(strokeWidth: 2),
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
                            style: Theme.of(context).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          ..._resultados.map(
                            (producto) => ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(producto.codigo),
                              subtitle: Text(producto.descripcion),
                              trailing: const Icon(Icons.add_circle_outline),
                              onTap: () => _addProducto(producto),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  'Productos contados (${_items.length})',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                Text(
                  'Unidades: ${_formatCantidad(_totalUnidades)}',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: const Color(0xFF4E6380)),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    if (_autosaving)
                      const SizedBox(
                        width: 14,
                        height: 14,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    else
                      Icon(
                        _pendingSync ? Icons.cloud_off_outlined : Icons.cloud_done_outlined,
                        size: 16,
                        color: _pendingSync ? const Color(0xFF946200) : const Color(0xFF087443),
                      ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        _syncStatus,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(color: const Color(0xFF4E6380)),
                      ),
                    ),
                  ],
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
                  ..._items.map(
                    (item) => Card(
                      child: ListTile(
                        title: Text(item.codigo),
                        subtitle: Text(item.descripcion),
                        trailing: SizedBox(
                          width: 144,
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: () => _editarCantidadItem(item),
                                  style: OutlinedButton.styleFrom(
                                    minimumSize: const Size(0, 40),
                                    padding: const EdgeInsets.symmetric(horizontal: 8),
                                  ),
                                  child: Text(_formatCantidad(item.cantidad)),
                                ),
                              ),
                              IconButton(
                                onPressed: () => _eliminarItem(item),
                                icon: const Icon(Icons.delete_outline),
                                color: Theme.of(context).colorScheme.error,
                                tooltip: 'Eliminar',
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
    );
  }
}
