import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_livreur/core/network/api_client.dart';
import 'package:ovanie_livreur/features/driver/data/driver_repository.dart';

void main() {
  test('Every selected registration attachment is sent with its own field', () async {
    final directory = await Directory.systemTemp.createTemp('ovanie-upload-');
    addTearDown(() async => directory.delete(recursive: true));
    final photo = await File('${directory.path}/photo.png').writeAsBytes([1, 2, 3]);
    FormData? payload;
    final interceptor = InterceptorsWrapper(onRequest: (options, handler) {
      payload = options.data as FormData;
      handler.resolve(Response(requestOptions: options, statusCode: 200, data: {'ok': true}));
    });
    ApiClient.dio.interceptors.add(interceptor);
    addTearDown(() => ApiClient.dio.interceptors.remove(interceptor));
    await DriverRepository.instance.submitOnboarding(
      profilePhoto: photo, zoneIds: [12], availabilities: ['Lundi'],
      vehicleType: 'Tricycle', plateNumber: 'AA-1234-GC',
      vehicleFiles: VehicleFiles(vehiclePhoto: photo, platePhoto: photo,
        vehicleRegistrationDocument: photo, supportingDocuments: [photo, photo]),
    );
    expect(payload!.files.map((entry) => entry.key), unorderedEquals([
      'photo', 'vehicle_photo', 'plate_photo', 'vehicle_registration_document',
      'supporting_documents[0]', 'supporting_documents[1]',
    ]));
    expect(Map.fromEntries(payload!.fields), containsPair('vehicle', 'tricycle'));
    expect(Map.fromEntries(payload!.fields), containsPair('availability_days[0]', 'lundi'));
  });
}
