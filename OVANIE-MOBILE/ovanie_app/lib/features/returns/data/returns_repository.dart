import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../domain/return_model.dart';

class ReturnsRepository {
  const ReturnsRepository();

  Future<ReturnCenterData> fetchCenter() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/returns');
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map) {
      throw const OvanieApiException('Les retours et réclamations sont indisponibles.');
    }
    return ReturnCenterData.fromJson(Map<String, dynamic>.from(body));
  }

  Future<ClientReturnCase> fetchCase(int id) async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/returns/$id');
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map) {
      throw const OvanieApiException('Ce dossier est indisponible.');
    }
    final data = body['data'] is Map
        ? Map<String, dynamic>.from(body['data'] as Map)
        : Map<String, dynamic>.from(body);
    return ClientReturnCase.fromJson(data);
  }

  Future<ClientReturnCase> submit({
    int? existingReturnId,
    required int orderId,
    required int orderItemId,
    required String type,
    required int quantity,
    required String reason,
    required String detailedDescription,
    DateTime? discoveryDate,
    String storageLocation = '',
    String pickupAddress = '',
    String pickupContactName = '',
    String pickupContactPhone = '',
    String pickupContactEmail = '',
    DateTime? pickupDate,
    String pickupTimeSlot = '',
    List<String> photoPaths = const [],
    List<String> videoPaths = const [],
  }) async {
    String dateOnly(DateTime value) {
      String two(int n) => n.toString().padLeft(2, '0');
      return '${value.year}-${two(value.month)}-${two(value.day)}';
    }

    final form = FormData.fromMap(<String, dynamic>{
      if (existingReturnId != null) 'existing_return_id': existingReturnId,
      'order_id': orderId,
      'order_item_id': orderItemId,
      'return_type': type,
      'quantity': quantity,
      'reason': reason,
      'detailed_description': detailedDescription,
      if (discoveryDate != null) 'discovery_date': dateOnly(discoveryDate),
      if (storageLocation.trim().isNotEmpty) 'storage_location': storageLocation.trim(),
      if (pickupAddress.trim().isNotEmpty) 'pickup_address': pickupAddress.trim(),
      if (pickupContactName.trim().isNotEmpty) 'pickup_contact_name': pickupContactName.trim(),
      if (pickupContactPhone.trim().isNotEmpty) 'pickup_contact_phone': pickupContactPhone.trim(),
      if (pickupContactEmail.trim().isNotEmpty) 'pickup_contact_email': pickupContactEmail.trim(),
      if (pickupDate != null) 'pickup_date': dateOnly(pickupDate),
      if (pickupTimeSlot.trim().isNotEmpty) 'pickup_time_slot': pickupTimeSlot.trim(),
    });

    for (final path in photoPaths.take(5)) {
      form.files.add(
        MapEntry(
          'photos[]',
          await MultipartFile.fromFile(
            path,
            filename: path.split(RegExp(r'[/\\]')).last,
          ),
        ),
      );
    }

    for (final path in videoPaths.take(1)) {
      form.files.add(
        MapEntry(
          'videos[]',
          await MultipartFile.fromFile(
            path,
            filename: path.split(RegExp(r'[/\\]')).last,
          ),
        ),
      );
    }

    final response = await ApiClient.dio.post<dynamic>(
      '/mobile/returns',
      data: form,
      options: Options(contentType: 'multipart/form-data'),
    );
    ApiClient.ensureSuccess(response);

    final body = response.data;
    if (body is! Map || body['data'] is! Map) {
      throw const OvanieApiException('Votre demande n’a pas pu être enregistrée.');
    }
    return ClientReturnCase.fromJson(
      Map<String, dynamic>.from(body['data'] as Map),
    );
  }
}
