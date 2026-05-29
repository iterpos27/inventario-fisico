import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../models/conteo_item.dart';
import '../models/producto.dart';
import '../models/toma.dart';

class ConteoDetalleSnapshot {
  const ConteoDetalleSnapshot({
    required this.items,
    required this.version,
  });

  final List<ConteoItem> items;
  final int version;
}

class ApiClient {
  ApiClient(
    this._prefs, {
    FlutterSecureStorage? secureStorage,
    String? initialToken,
  })  : _secureStorage = secureStorage ?? const FlutterSecureStorage(),
        _token = initialToken;

  static const _baseUrlKey = 'api_base_url';
  static const _tokenKey = 'api_token';

  final SharedPreferences _prefs;
  final FlutterSecureStorage _secureStorage;
  String? _token;

  String get baseUrl {
    final stored = _prefs.getString(_baseUrlKey);
    if (stored == null ||
        stored == 'https://10.0.2.2/centro_ruliman_inventario') {
      return 'http://10.0.2.2/centro_ruliman_inventario';
    }
    return stored;
  }

  String? get token => _token;
  bool get hasToken => token != null && token!.isNotEmpty;

  Future<void> loadToken() async {
    _token = await _secureStorage.read(key: _tokenKey);
    if (!hasToken) {
      final legacyToken = _prefs.getString(_tokenKey);
      if (legacyToken != null && legacyToken.isNotEmpty) {
        _token = legacyToken;
        await _secureStorage.write(key: _tokenKey, value: legacyToken);
        await _prefs.remove(_tokenKey);
      }
    }
  }

  Future<void> setBaseUrl(String value) async {
    await _prefs.setString(_baseUrlKey, value.replaceAll(RegExp(r'/+$'), ''));
  }

  Future<void> login({
    required String usuario,
    required String password,
    required String baseUrl,
  }) async {
    await setBaseUrl(baseUrl);
    final data = await _post(
        '/api/v1/login',
        {
          'usuario': usuario,
          'password': password,
          'device': 'Flutter Android',
        },
        authenticated: false);
    _token = '${data['token']}';
    await _secureStorage.write(key: _tokenKey, value: _token);
    await _prefs.remove(_tokenKey);
  }

  Future<void> logout() async {
    if (hasToken) {
      try {
        await _post('/api/v1/logout', {});
      } catch (_) {
        // La salida local debe funcionar aunque el servidor no responda.
      }
    }
    _token = null;
    await _secureStorage.delete(key: _tokenKey);
    await _prefs.remove(_tokenKey);
  }

  Future<List<Toma>> fetchTomas() async {
    final data = await _get('/api/v1/tomas');
    return (data['tomas'] as List? ?? [])
        .map((item) => Toma.fromJson(Map<String, dynamic>.from(item as Map)))
        .toList();
  }

  Future<int> iniciarConteo(int tomaId) async {
    final data = await _post('/api/v1/iniciar_conteo', {'toma_id': tomaId});
    return int.parse('${data['conteo_id']}');
  }

  Future<ConteoDetalleSnapshot> fetchDetalle(int conteoId) async {
    final data = await _get('/api/v1/detalle_conteo?conteo_id=$conteoId');
    final items = (data['items'] as List? ?? [])
        .map((item) =>
            ConteoItem.fromJson(Map<String, dynamic>.from(item as Map)))
        .toList();

    return ConteoDetalleSnapshot(
      items: items,
      version: int.tryParse('${data['conteo_version'] ?? 0}') ?? 0,
    );
  }

  Future<List<Producto>> buscarProductos(String q) async {
    final data =
        await _get('/api/v1/productos?q=${Uri.encodeQueryComponent(q)}');
    return (data['productos'] as List? ?? [])
        .map(
            (item) => Producto.fromJson(Map<String, dynamic>.from(item as Map)))
        .toList();
  }

  Future<int> guardarBorrador(
      int conteoId, List<ConteoItem> items, int conteoVersion) async {
    final data = await _post('/api/v1/guardar_borrador', {
      'conteo_id': conteoId,
      'conteo_version': conteoVersion,
      'items': items.map((item) => item.toJson()).toList(),
    });
    return int.tryParse('${data['conteo_version'] ?? conteoVersion}') ??
        conteoVersion;
  }

  Future<String?> finalizarConteo(
      int conteoId, List<ConteoItem> items, int conteoVersion) async {
    final data = await _post('/api/v1/finalizar_conteo', {
      'conteo_id': conteoId,
      'conteo_version': conteoVersion,
      'items': items.map((item) => item.toJson()).toList(),
    });
    final url = data['download_url'];
    return url == null ? null : '$baseUrl$url';
  }

  Future<Map<String, dynamic>> _get(String path) async {
    try {
      final response = await http
          .get(Uri.parse('$baseUrl$path'), headers: _headers())
          .timeout(const Duration(seconds: 15));
      return _decode(response);
    } on HandshakeException {
      throw const ApiException(
          'Error SSL. Para XAMPP/local use http:// en la URL del servidor.');
    } on SocketException {
      throw const ApiException(
          'No se pudo conectar al servidor. Revise la URL y la red.');
    } on TimeoutException {
      throw const ApiException(
          'Tiempo de espera agotado. Revise la conexion al servidor.');
    }
  }

  Future<Map<String, dynamic>> _post(
    String path,
    Map<String, dynamic> body, {
    bool authenticated = true,
  }) async {
    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl$path'),
            headers: _headers(authenticated: authenticated),
            body: jsonEncode(body),
          )
          .timeout(const Duration(seconds: 15));
      return _decode(response);
    } on HandshakeException {
      throw const ApiException(
          'Error SSL. Para XAMPP/local use http:// en la URL del servidor.');
    } on SocketException {
      throw const ApiException(
          'No se pudo conectar al servidor. Revise la URL y la red.');
    } on TimeoutException {
      throw const ApiException(
          'Tiempo de espera agotado. Revise la conexion al servidor.');
    }
  }

  Map<String, String> _headers({bool authenticated = true}) {
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (authenticated && hasToken) 'Authorization': 'Bearer $token',
    };
  }

  Map<String, dynamic> _decode(http.Response response) {
    final dynamic body;
    try {
      body = jsonDecode(response.body.isEmpty ? '{}' : response.body);
    } catch (_) {
      throw const ApiException('Respuesta invalida del servidor');
    }
    if (body is! Map) {
      throw const ApiException('Respuesta invalida del servidor');
    }
    final data = Map<String, dynamic>.from(body);
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
