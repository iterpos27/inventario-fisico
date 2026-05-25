import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../models/conteo_item.dart';
import '../models/producto.dart';
import '../models/toma.dart';

class ApiClient {
  ApiClient(this._prefs);

  static const _baseUrlKey = 'api_base_url';
  static const _tokenKey = 'api_token';

  final SharedPreferences _prefs;

  String get baseUrl => _prefs.getString(_baseUrlKey) ?? 'http://10.0.2.2/centro_ruliman_inventario';
  String? get token => _prefs.getString(_tokenKey);
  bool get hasToken => token != null && token!.isNotEmpty;

  Future<void> setBaseUrl(String value) async {
    await _prefs.setString(_baseUrlKey, value.replaceAll(RegExp(r'/+$'), ''));
  }

  Future<void> login({
    required String usuario,
    required String password,
    required String baseUrl,
  }) async {
    await setBaseUrl(baseUrl);
    final data = await _post('/api/login', {
      'usuario': usuario,
      'password': password,
      'device': 'Flutter Android',
    }, authenticated: false);
    await _prefs.setString(_tokenKey, '${data['token']}');
  }

  Future<void> logout() async {
    if (hasToken) {
      try {
        await _post('/api/logout', {});
      } catch (_) {
        // La salida local debe funcionar aunque el servidor no responda.
      }
    }
    await _prefs.remove(_tokenKey);
  }

  Future<List<Toma>> fetchTomas() async {
    final data = await _get('/api/tomas');
    return (data['tomas'] as List? ?? [])
        .map((item) => Toma.fromJson(Map<String, dynamic>.from(item as Map)))
        .toList();
  }

  Future<int> iniciarConteo(int tomaId) async {
    final data = await _post('/api/iniciar_conteo', {'toma_id': tomaId});
    return int.parse('${data['conteo_id']}');
  }

  Future<List<ConteoItem>> fetchDetalle(int conteoId) async {
    final data = await _get('/api/detalle_conteo?conteo_id=$conteoId');
    return (data['items'] as List? ?? [])
        .map((item) => ConteoItem.fromJson(Map<String, dynamic>.from(item as Map)))
        .toList();
  }

  Future<List<Producto>> buscarProductos(String q) async {
    final data = await _get('/api/productos?q=${Uri.encodeQueryComponent(q)}');
    return (data['productos'] as List? ?? [])
        .map((item) => Producto.fromJson(Map<String, dynamic>.from(item as Map)))
        .toList();
  }

  Future<void> guardarBorrador(int conteoId, List<ConteoItem> items) async {
    await _post('/api/guardar_borrador', {
      'conteo_id': conteoId,
      'items': items.map((item) => item.toJson()).toList(),
    });
  }

  Future<String?> finalizarConteo(int conteoId, List<ConteoItem> items) async {
    final data = await _post('/api/finalizar_conteo', {
      'conteo_id': conteoId,
      'items': items.map((item) => item.toJson()).toList(),
    });
    final url = data['download_url'];
    return url == null ? null : '$baseUrl$url';
  }

  Future<Map<String, dynamic>> _get(String path) async {
    final response = await http.get(Uri.parse('$baseUrl$path'), headers: _headers());
    return _decode(response);
  }

  Future<Map<String, dynamic>> _post(
    String path,
    Map<String, dynamic> body, {
    bool authenticated = true,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl$path'),
      headers: _headers(authenticated: authenticated),
      body: jsonEncode(body),
    );
    return _decode(response);
  }

  Map<String, String> _headers({bool authenticated = true}) {
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (authenticated && hasToken) 'Authorization': 'Bearer $token',
    };
  }

  Map<String, dynamic> _decode(http.Response response) {
    final body = jsonDecode(response.body.isEmpty ? '{}' : response.body);
    final data = Map<String, dynamic>.from(body as Map);
    if (response.statusCode >= 400 || data['ok'] == false) {
      throw ApiException('${data['message'] ?? 'Error de servidor'}');
    }
    return data;
  }
}

class ApiException implements Exception {
  const ApiException(this.message);
  final String message;

  @override
  String toString() => message;
}
