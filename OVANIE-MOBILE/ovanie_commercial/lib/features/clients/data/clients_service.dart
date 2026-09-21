import '../../../core/api/api_client.dart';
import '../models/client_data.dart';

class ClientsService {
  ClientsService(this._api);

  final ApiClient _api;

  Future<CommercialClientsData> fetch({
    String query = '',
    ClientFilter filter = ClientFilter.all,
  }) async {
    final json = await _api.getJson(
      '/mobile/v1/commercial/clients',
      queryParameters: {
        if (query.trim().isNotEmpty) 'q': query.trim(),
        'status': filter.apiValue,
      },
    );
    return CommercialClientsData.fromJson(json);
  }

  Future<CreateCommercialClientResult> create(
    CreateCommercialClientPayload payload,
  ) async {
    final json = await _api.postJson(
      '/mobile/v1/commercial/clients',
      body: payload.toJson(),
    );
    return CreateCommercialClientResult.fromJson(json);
  }
}
