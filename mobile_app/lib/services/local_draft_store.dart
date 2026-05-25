import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/conteo_item.dart';

class LocalDraftStore {
  LocalDraftStore(this._prefs);

  final SharedPreferences _prefs;

  String _key(int conteoId) => 'draft_$conteoId';

  Future<void> save(int conteoId, List<ConteoItem> items) async {
    await _prefs.setString(
      _key(conteoId),
      jsonEncode(items.map((item) => item.toJson()).toList()),
    );
  }

  List<ConteoItem> load(int conteoId) {
    final raw = _prefs.getString(_key(conteoId));
    if (raw == null || raw.isEmpty) {
      return [];
    }
    final list = jsonDecode(raw) as List;
    return list
        .map((item) => ConteoItem.fromJson(Map<String, dynamic>.from(item as Map)))
        .toList();
  }

  Future<void> clear(int conteoId) => _prefs.remove(_key(conteoId));
}
