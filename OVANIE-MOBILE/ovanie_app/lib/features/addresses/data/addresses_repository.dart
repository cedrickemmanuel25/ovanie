import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../domain/client_address.dart';

class AddressTypeOption {
  final String code;
  final String label;

  const AddressTypeOption({required this.code, required this.label});

  factory AddressTypeOption.fromJson(Map<String, dynamic> json) {
    return AddressTypeOption(
      code: (json['code'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
    );
  }
}

class AddressesPayload {
  final List<ClientAddress> addresses;
  final List<AddressTypeOption> types;

  const AddressesPayload({required this.addresses, required this.types});
}

class AddressesRepository {
  const AddressesRepository();

  Future<AddressesPayload> fetch() async {
    final response = await ApiClient.dio.get<dynamic>('/addresses');
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map) return const AddressesPayload(addresses: [], types: []);

    final addresses = body['data'] is List
        ? (body['data'] as List)
            .whereType<Map>()
            .map((item) => ClientAddress.fromJson(Map<String, dynamic>.from(item)))
            .where((item) => item.id > 0)
            .toList(growable: false)
        : <ClientAddress>[];
    final types = body['types'] is List
        ? (body['types'] as List)
            .whereType<Map>()
            .map((item) => AddressTypeOption.fromJson(Map<String, dynamic>.from(item)))
            .where((item) => item.code.isNotEmpty)
            .toList(growable: false)
        : <AddressTypeOption>[];

    return AddressesPayload(addresses: addresses, types: types);
  }

  Future<ClientAddress> create(ClientAddressDraft draft) async {
    final response = await ApiClient.dio.post<dynamic>('/addresses', data: draft.toJson());
    ApiClient.ensureSuccess(response);
    return _addressFromResponse(response.data);
  }

  Future<ClientAddress> update(int id, ClientAddressDraft draft) async {
    final response = await ApiClient.dio.put<dynamic>('/addresses/$id', data: draft.toJson());
    ApiClient.ensureSuccess(response);
    return _addressFromResponse(response.data);
  }

  Future<void> delete(int id) async {
    final response = await ApiClient.dio.delete<dynamic>('/addresses/$id');
    ApiClient.ensureSuccess(response);
  }

  Future<ClientAddress> setDefault(int id) async {
    final response = await ApiClient.dio.post<dynamic>('/addresses/$id/default');
    ApiClient.ensureSuccess(response);
    return _addressFromResponse(response.data);
  }

  ClientAddress _addressFromResponse(dynamic raw) {
    if (raw is! Map || raw['data'] is! Map) {
      throw const OvanieApiException('La réponse adresse OVANIE est invalide.');
    }
    return ClientAddress.fromJson(Map<String, dynamic>.from(raw['data'] as Map));
  }
}
