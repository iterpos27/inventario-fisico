import 'package:flutter/material.dart';

import '../services/api_client.dart';
import 'tomas_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _baseUrl = TextEditingController();
  final _usuario = TextEditingController();
  final _password = TextEditingController();
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _baseUrl.text = widget.api.baseUrl;
  }

  @override
  void dispose() {
    _baseUrl.dispose();
    _usuario.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    setState(() => _loading = true);
    try {
      await widget.api.login(
        usuario: _usuario.text.trim(),
        password: _password.text,
        baseUrl: _baseUrl.text.trim(),
      );
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => TomasScreen(api: widget.api)),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$error')),
      );
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const SizedBox(height: 48),
            Container(
              width: 72,
              height: 72,
              alignment: Alignment.center,
              decoration: const BoxDecoration(
                color: Color(0xFF004080),
                shape: BoxShape.circle,
              ),
              child: const Text(
                'CR',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800),
              ),
            ),
            const SizedBox(height: 24),
            Text(
              'Centro del Ruliman',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF004080),
                  ),
            ),
            const SizedBox(height: 8),
            Text(
              'Conteo de inventario',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 32),
            TextField(
              controller: _baseUrl,
              decoration: const InputDecoration(
                labelText: 'URL del servidor',
                hintText: 'http://10.0.2.2/centro_ruliman_inventario',
              ),
              keyboardType: TextInputType.url,
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _usuario,
              decoration: const InputDecoration(labelText: 'Usuario'),
              textInputAction: TextInputAction.next,
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _password,
              decoration: const InputDecoration(labelText: 'Contrasena'),
              obscureText: true,
              onSubmitted: (_) => _loading ? null : _login(),
            ),
            const SizedBox(height: 22),
            ElevatedButton(
              onPressed: _loading ? null : _login,
              child: _loading
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Ingresar'),
            ),
          ],
        ),
      ),
    );
  }
}
