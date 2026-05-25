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
  final _buscar = TextEditingController();
  final _cantidad = TextEditingController(text: '1');
  final List<ConteoItem> _items = [];
  late LocalDraftStore _store;
  bool _loading = true;
  bool _saving = false;
  List<Producto> _resultados = [];

  @override
  void initState() {
    super.initState();
    _init();
  }

  @override
  void dispose() {
    _buscar.dispose();
    _cantidad.dispose();
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
  }

  Future<void> _buscarProductos([String? term]) async {
    final q = (term ?? _buscar.text).trim();
    if (q.length < 2) {
      setState(() => _resultados = []);
      return;
    }
    try {
      final productos = await widget.api.buscarProductos(q);
      if (mounted) {
        setState(() => _resultados = productos);
      }
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
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
    final cantidad = double.tryParse(_cantidad.text.replaceAll(',', '.')) ?? 0;
    if (cantidad <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ingrese una cantidad valida')),
      );
      return;
    }

    final existingIndex = _items.indexWhere((item) => item.productoId == producto.id);
    var message = 'Producto agregado al inicio';
    setState(() {
      if (existingIndex >= 0) {
        final item = _items.removeAt(existingIndex);
        item.cantidad += cantidad;
        _items.insert(0, item);
        message = 'Este codigo ya se conto. Cantidad actualizada a ${_formatQuantity(item.cantidad)}';
      } else {
        _items.insert(0, ConteoItem.fromProducto(producto, cantidad));
      }
      _buscar.clear();
      _cantidad.text = '1';
      _resultados = [];
    });
    await _store.save(widget.conteoId, _items);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  String _formatQuantity(double value) {
    return value.truncateToDouble() == value ? value.toStringAsFixed(0) : value.toStringAsFixed(2);
  }

  Future<void> _guardar() async {
    if (_items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Agregue productos antes de guardar')),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.api.guardarBorrador(widget.conteoId, _items);
      await _store.save(widget.conteoId, _items);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Borrador guardado')),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Guardado local. Sincronice luego. $error')),
      );
      await _store.save(widget.conteoId, _items);
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
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
                                decoration: const InputDecoration(
                                  labelText: 'Codigo o descripcion',
                                  prefixIcon: Icon(Icons.search),
                                ),
                                onChanged: _buscarProductos,
                              ),
                            ),
                            const SizedBox(width: 10),
                            IconButton.filled(
                              onPressed: _scan,
                              icon: const Icon(Icons.qr_code_scanner),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        TextField(
                          controller: _cantidad,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: const InputDecoration(
                            labelText: 'Cantidad',
                            prefixIcon: Icon(Icons.tag),
                          ),
                        ),
                        if (_resultados.isNotEmpty) ...[
                          const SizedBox(height: 12),
                          ..._resultados.map(
                            (producto) => ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(producto.descripcion),
                              subtitle: Text(producto.codigo),
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
                        title: Text(item.descripcion),
                        subtitle: Text(item.codigo),
                        trailing: SizedBox(
                          width: 96,
                          child: TextFormField(
                            key: ValueKey('${item.productoId}-${item.cantidad}'),
                            initialValue: _formatQuantity(item.cantidad),
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            textAlign: TextAlign.end,
                            decoration: const InputDecoration(isDense: true),
                            onChanged: (value) async {
                              item.cantidad = double.tryParse(value.replaceAll(',', '.')) ?? item.cantidad;
                              await _store.save(widget.conteoId, _items);
                            },
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
