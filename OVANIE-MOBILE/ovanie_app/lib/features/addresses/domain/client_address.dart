import '../../../core/utils/text_cleaner.dart';

class ClientAddress {
  final int id;
  final String type;
  final String typeLabel;
  final String label;
  final String recipientName;
  final String city;
  final String commune;
  final String quartier;
  final String quartierPrincipal;
  final String sousQuartier;
  final String localityType;
  final String localityTypeLabel;
  final String country;
  final String address;
  final String phone;
  final double? latitude;
  final double? longitude;
  final bool isDefault;

  const ClientAddress({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.label,
    required this.recipientName,
    required this.city,
    required this.commune,
    required this.quartier,
    required this.quartierPrincipal,
    required this.sousQuartier,
    required this.localityType,
    required this.localityTypeLabel,
    required this.country,
    required this.address,
    required this.phone,
    required this.latitude,
    required this.longitude,
    required this.isDefault,
  });

  factory ClientAddress.fromJson(Map<String, dynamic> json) {
    double? number(dynamic value) {
      if (value == null || '$value'.trim().isEmpty) return null;
      if (value is num) return value.toDouble();
      return double.tryParse('$value');
    }

    String text(dynamic value) => cleanOvanieText((value ?? '').toString());

    return ClientAddress(
      id: int.tryParse('${json['id'] ?? ''}') ?? 0,
      type: (json['type'] ?? 'home').toString(),
      typeLabel: text(json['type_label'] ?? 'Adresse'),
      label: text(json['label'] ?? 'Adresse'),
      recipientName: text(json['recipient_name']),
      city: text(json['city']),
      commune: text(json['commune']),
      quartier: text(json['quartier']),
      quartierPrincipal: text(json['quartier_principal']),
      sousQuartier: text(json['sous_quartier']),
      localityType: text(json['locality_type']),
      localityTypeLabel: text(json['locality_type_label']),
      country: text(json['country'] ?? "Côte d'Ivoire"),
      address: text(json['address']),
      phone: (json['phone'] ?? '').toString().trim(),
      latitude: number(json['latitude']),
      longitude: number(json['longitude']),
      isDefault: json['is_default'] == true || '${json['is_default']}' == '1',
    );
  }

  String get locationSummary {
    final parts = <String>[
      if (commune.trim().isNotEmpty) commune.trim(),
      if (quartierPrincipal.trim().isNotEmpty && quartierPrincipal.trim() != commune.trim())
        quartierPrincipal.trim(),
      if (sousQuartier.trim().isNotEmpty && sousQuartier.trim() != quartierPrincipal.trim())
        sousQuartier.trim()
      else if (quartier.trim().isNotEmpty && quartier.trim() != commune.trim())
        quartier.trim(),
    ];
    return parts.toSet().join(' · ');
  }
}

class ClientAddressDraft {
  final String type;
  final String label;
  final String recipientName;
  final String city;
  final String commune;
  final String quartier;
  final String address;
  final String phone;
  final double? latitude;
  final double? longitude;
  final bool isDefault;

  const ClientAddressDraft({
    required this.type,
    required this.label,
    required this.recipientName,
    required this.city,
    required this.commune,
    required this.quartier,
    required this.address,
    required this.phone,
    required this.latitude,
    required this.longitude,
    required this.isDefault,
  });

  Map<String, dynamic> toJson() => <String, dynamic>{
        'type': type,
        'label': label.trim(),
        'recipient_name': recipientName.trim(),
        'city': city.trim(),
        'commune': commune.trim(),
        'quartier': quartier.trim().isEmpty ? null : quartier.trim(),
        'country': "Côte d'Ivoire",
        'address': address.trim(),
        'phone': phone.trim(),
        'latitude': latitude,
        'longitude': longitude,
        'is_default': isDefault,
      };
}
