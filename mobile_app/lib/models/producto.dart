class Producto {
  const Producto({
    required this.id,
    required this.codigo,
    required this.descripcion,
  });

  final int id;
  final String codigo;
  final String descripcion;

  factory Producto.fromJson(Map<String, dynamic> json) {
    return Producto(
      id: int.parse('${json['id']}'),
      codigo: '${json['codigo'] ?? ''}',
      descripcion: '${json['descripcion'] ?? ''}',
    );
  }
}
