class Toma {
  const Toma({
    required this.tomaId,
    required this.numeroToma,
    required this.nombreToma,
    required this.tomaEstado,
    required this.asignacionEstado,
    this.agencia,
    this.conteoId,
    this.conteoEstado,
  });

  final int tomaId;
  final String numeroToma;
  final String nombreToma;
  final String tomaEstado;
  final String asignacionEstado;
  final String? agencia;
  final int? conteoId;
  final String? conteoEstado;

  bool get estaDisponible => tomaEstado == 'abierta' && conteoEstado != 'finalizado';

  factory Toma.fromJson(Map<String, dynamic> json) {
    final rawConteoId = json['conteo_id'];
    return Toma(
      tomaId: int.parse('${json['toma_id']}'),
      numeroToma: '${json['numero_toma'] ?? ''}',
      nombreToma: '${json['nombre_toma'] ?? ''}',
      tomaEstado: '${json['toma_estado'] ?? ''}',
      asignacionEstado: '${json['asignacion_estado'] ?? ''}',
      agencia: json['agencia'] == null ? null : '${json['agencia']}',
      conteoId: rawConteoId == null ? null : int.tryParse('$rawConteoId'),
      conteoEstado: json['conteo_estado'] == null ? null : '${json['conteo_estado']}',
    );
  }
}
