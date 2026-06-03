import 'package:flutter/material.dart';

import '../models/toma.dart';
import '../services/api_client.dart';
import 'conteo_screen.dart';
import 'login_screen.dart';

class TomasScreen extends StatefulWidget {
  const TomasScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<TomasScreen> createState() => _TomasScreenState();
}

class _TomasScreenState extends State<TomasScreen> {
  late Future<List<Toma>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.api.fetchTomas();
  }

  void _reload() {
    setState(() {
      _future = widget.api.fetchTomas();
    });
  }

  Future<void> _logout() async {
    await widget.api.logout();
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => LoginScreen(api: widget.api)),
    );
  }

  Future<void> _abrirToma(Toma toma) async {
    if (!toma.estaDisponible) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Esta toma no esta disponible para editar')),
      );
      return;
    }

    try {
      final conteoId =
          toma.conteoId ?? await widget.api.iniciarConteo(toma.tomaId);
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) =>
              ConteoScreen(api: widget.api, toma: toma, conteoId: conteoId),
        ),
      );
      _reload();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tomas asignadas'),
        actions: [
          IconButton(onPressed: _reload, icon: const Icon(Icons.refresh)),
          IconButton(onPressed: _logout, icon: const Icon(Icons.logout)),
        ],
      ),
      body: FutureBuilder<List<Toma>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _MessageState(
              title: 'No se pudo cargar',
              message: '${snapshot.error}',
              onRetry: _reload,
            );
          }
          final tomas = snapshot.data ?? [];
          if (tomas.isEmpty) {
            return _MessageState(
              title: 'Sin tomas',
              message: 'No tienes tomas asignadas.',
              onRetry: _reload,
            );
          }
          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 18),
              itemCount: tomas.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final toma = tomas[index];
                return Card(
                  child: InkWell(
                    borderRadius: BorderRadius.circular(8),
                    onTap: () => _abrirToma(toma),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  toma.numeroToma,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w900,
                                    color: Color(0xFF004080),
                                    fontSize: 16,
                                  ),
                                ),
                              ),
                              _StatusBadge(
                                  text: toma.conteoEstado ??
                                      toma.asignacionEstado),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            toma.nombreToma,
                            maxLines: 4,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Color(0xFF101828),
                              fontWeight: FontWeight.w700,
                              height: 1.25,
                            ),
                          ),
                          if (toma.agencia != null &&
                              toma.agencia!.isNotEmpty) ...[
                            const SizedBox(height: 10),
                            _InfoChip(
                              icon: Icons.storefront_outlined,
                              text: toma.agencia!,
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
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
          Flexible(
            child: Text(
              text,
              style: const TextStyle(
                color: Color(0xFF004080),
                fontWeight: FontWeight.w800,
                fontSize: 12,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    final done = text == 'finalizado';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: done ? const Color(0xFFE4F6EC) : const Color(0xFFFFF6D8),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: done ? const Color(0xFF087443) : const Color(0xFF946200),
          fontWeight: FontWeight.w700,
          fontSize: 12,
        ),
      ),
    );
  }
}

class _MessageState extends StatelessWidget {
  const _MessageState({
    required this.title,
    required this.message,
    required this.onRetry,
  });

  final String title;
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 18),
            OutlinedButton(onPressed: onRetry, child: const Text('Reintentar')),
          ],
        ),
      ),
    );
  }
}
