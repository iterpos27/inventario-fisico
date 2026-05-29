import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import '../models/conteo_item.dart';

class LocalDraftStore {
  LocalDraftStore._(this._db);

  final Database _db;

  static Future<LocalDraftStore> open() async {
    final dbPath = p.join(await getDatabasesPath(), 'conteo_drafts.db');
    final db = await openDatabase(
      dbPath,
      version: 1,
      onCreate: (database, version) async {
        await database.execute('''
          CREATE TABLE draft_items (
            conteo_id INTEGER NOT NULL,
            producto_id INTEGER NOT NULL,
            codigo TEXT NOT NULL,
            descripcion TEXT NOT NULL,
            cantidad REAL NOT NULL,
            position INTEGER NOT NULL,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (conteo_id, producto_id)
          )
        ''');
        await database.execute('CREATE INDEX idx_draft_items_conteo_position ON draft_items (conteo_id, position)');
      },
    );

    return LocalDraftStore._(db);
  }

  Future<void> save(int conteoId, List<ConteoItem> items) async {
    final now = DateTime.now().toIso8601String();
    await _db.transaction((txn) async {
      await txn.delete('draft_items', where: 'conteo_id = ?', whereArgs: [conteoId]);
      for (var index = 0; index < items.length; index += 1) {
        final item = items[index];
        await txn.insert('draft_items', {
          'conteo_id': conteoId,
          'producto_id': item.productoId,
          'codigo': item.codigo,
          'descripcion': item.descripcion,
          'cantidad': item.cantidad,
          'position': index,
          'updated_at': now,
        });
      }
    });
  }

  Future<List<ConteoItem>> load(int conteoId) async {
    final rows = await _db.query(
      'draft_items',
      where: 'conteo_id = ?',
      whereArgs: [conteoId],
      orderBy: 'position ASC',
    );

    return rows
        .map(
          (row) => ConteoItem(
            productoId: row['producto_id'] as int,
            codigo: '${row['codigo']}',
            descripcion: '${row['descripcion']}',
            cantidad: (row['cantidad'] as num).toDouble(),
          ),
        )
        .toList();
  }

  Future<void> clear(int conteoId) => _db.delete('draft_items', where: 'conteo_id = ?', whereArgs: [conteoId]);

  Future<void> close() => _db.close();
}
