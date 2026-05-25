import 'producto.dart';

class ConteoItem {
  ConteoItem({
    required this.productoId,
    required this.codigo,
    required this.descripcion,
    required this.cantidad,
  });

  final int productoId;
  final String codigo;
  final String descripcion;
  double cantidad;

  factory ConteoItem.fromProducto(Producto producto, double cantidad) {
    return ConteoItem(
      productoId: producto.id,
      codigo: producto.codigo,
      descripcion: producto.descripcion,
      cantidad: cantidad,
    );
  }

  factory ConteoItem.fromJson(Map<String, dynamic> json) {
    return ConteoItem(
      productoId: int.parse('${json['producto_id']}'),
      codigo: '${json['codigo'] ?? ''}',
      descripcion: '${json['descripcion'] ?? ''}',
      cantidad: double.tryParse('${json['cantidad']}') ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'producto_id': productoId,
      'codigo': codigo,
      'descripcion': descripcion,
      'cantidad': cantidad,
    };
  }
}
