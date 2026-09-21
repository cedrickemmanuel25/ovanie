import '../../dashboard/models/dashboard_data.dart';

enum ClientFilter {
  all,
  active,
  prospect,
  inactive;

  String get apiValue => switch (this) {
        ClientFilter.all => 'all',
        ClientFilter.active => 'active',
        ClientFilter.prospect => 'prospect',
        ClientFilter.inactive => 'inactive',
      };
}

class ClientSummary {
  const ClientSummary({
    required this.all,
    required this.active,
    required this.prospects,
    required this.inactive,
  });

  final int all;
  final int active;
  final int prospects;
  final int inactive;

  factory ClientSummary.fromJson(Map<String, dynamic> json) {
    int value(String key) => (json[key] as num?)?.toInt() ?? 0;
    return ClientSummary(
      all: value('all'),
      active: value('active'),
      prospects: value('prospects'),
      inactive: value('inactive'),
    );
  }

  static const empty = ClientSummary(all: 0, active: 0, prospects: 0, inactive: 0);
}

class CommercialClient {
  const CommercialClient({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    required this.accountType,
    required this.status,
    required this.city,
    required this.communeQuartier,
    required this.ordersCount,
    required this.lastOrderAt,
  });

  final int id;
  final String name;
  final String email;
  final String phone;
  final String accountType;
  final String status;
  final String city;
  final String communeQuartier;
  final int ordersCount;
  final DateTime? lastOrderAt;

  bool get isEnterprise => accountType == 'entreprise';

  String get accountTypeLabel => isEnterprise ? 'Entreprise' : 'Particulier';

  String get statusLabel => switch (status) {
        'active' => 'Actif',
        'prospect' => 'Prospect',
        'inactive' => 'Inactif',
        _ => 'Actif',
      };

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList();
    if (parts.isEmpty) return 'CL';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }

  String get locationLabel {
    final parts = <String>[];
    if (city.trim().isNotEmpty) parts.add(city.trim());
    if (communeQuartier.trim().isNotEmpty &&
        !parts.any((value) => value.toLowerCase() == communeQuartier.trim().toLowerCase())) {
      parts.add(communeQuartier.trim());
    }
    return parts.isEmpty ? 'Localisation non renseignée' : parts.join(', ');
  }

  factory CommercialClient.fromJson(Map<String, dynamic> json) {
    return CommercialClient(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: (json['name'] ?? '').toString().trim(),
      email: (json['email'] ?? '').toString().trim(),
      phone: (json['phone'] ?? '').toString().trim(),
      accountType: (json['account_type'] ?? 'particulier').toString().toLowerCase(),
      status: (json['client_status'] ?? 'active').toString().toLowerCase(),
      city: (json['city'] ?? '').toString().trim(),
      communeQuartier: (json['commune_quartier'] ?? '').toString().trim(),
      ordersCount: (json['orders_count'] as num?)?.toInt() ?? 0,
      lastOrderAt: DateTime.tryParse((json['last_order_at'] ?? '').toString()),
    );
  }
}

class CommercialClientsData {
  const CommercialClientsData({
    required this.profile,
    required this.unreadNotifications,
    required this.summary,
    required this.clients,
  });

  final CommercialProfile profile;
  final int unreadNotifications;
  final ClientSummary summary;
  final List<CommercialClient> clients;

  factory CommercialClientsData.fromJson(Map<String, dynamic> json) {
    final rawClients = json['clients'];
    return CommercialClientsData(
      profile: CommercialProfile.fromJson(
        json['profile'] is Map ? Map<String, dynamic>.from(json['profile'] as Map) : const {},
      ),
      unreadNotifications: (json['unread_notifications'] as num?)?.toInt() ?? 0,
      summary: ClientSummary.fromJson(
        json['summary'] is Map ? Map<String, dynamic>.from(json['summary'] as Map) : const {},
      ),
      clients: rawClients is List
          ? rawClients
              .whereType<Map>()
              .map((item) => CommercialClient.fromJson(Map<String, dynamic>.from(item)))
              .toList(growable: false)
          : const [],
    );
  }
}

class CreateCommercialClientPayload {
  const CreateCommercialClientPayload({
    required this.clientType,
    required this.fullName,
    required this.email,
    required this.phoneCountry,
    required this.phone,
    required this.city,
    required this.communeQuartier,
    required this.notes,
    required this.password,
    required this.passwordConfirmation,
    required this.sendCredentials,
  });

  final String clientType;
  final String fullName;
  final String email;
  final String phoneCountry;
  final String phone;
  final String city;
  final String communeQuartier;
  final String notes;
  final String password;
  final String passwordConfirmation;
  final bool sendCredentials;

  Map<String, dynamic> toJson() => {
        'client_type': clientType,
        'full_name': fullName.trim(),
        'email': email.trim(),
        'phone_country': phoneCountry,
        'phone': phone.trim(),
        'city': city.trim(),
        'commune_quartier': communeQuartier.trim(),
        'commercial_notes': notes.trim().isEmpty ? null : notes.trim(),
        'password': password,
        'password_confirmation': passwordConfirmation,
        'send_credentials': sendCredentials,
      };
}

class CreateCommercialClientResult {
  const CreateCommercialClientResult({
    required this.client,
    required this.message,
    required this.emailSent,
    required this.smsSent,
  });

  final CommercialClient client;
  final String message;
  final bool emailSent;
  final bool smsSent;

  factory CreateCommercialClientResult.fromJson(Map<String, dynamic> json) {
    final clientJson = json['client'] is Map
        ? Map<String, dynamic>.from(json['client'] as Map)
        : const <String, dynamic>{};
    final delivery = json['credential_delivery'] is Map
        ? Map<String, dynamic>.from(json['credential_delivery'] as Map)
        : const <String, dynamic>{};
    return CreateCommercialClientResult(
      client: CommercialClient.fromJson(clientJson),
      message: (json['message'] ?? 'Client créé avec succès.').toString(),
      emailSent: delivery['email'] == true,
      smsSent: delivery['sms'] == true,
    );
  }
}
