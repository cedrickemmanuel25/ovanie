import 'dart:io';

import 'package:dio/dio.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/network/api_client.dart';
import '../domain/support_models.dart';

class SupportRepository {
  const SupportRepository();

  Map<String, dynamic> _data(dynamic body) {
    if (body is! Map || body['data'] is! Map) throw const OvanieApiException('Centre d’assistance indisponible.');
    return Map<String, dynamic>.from(body['data'] as Map);
  }

  Future<SupportCenterData> center() async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/support');
    ApiClient.ensureSuccess(response);
    return SupportCenterData.fromJson(_data(response.data));
  }

  Future<void> createTicket({required String category, required String subject, required String description, List<Map<String, dynamic>> contexts = const [], List<String> attachmentPaths = const []}) async {
    final form = FormData.fromMap({
      'category': category,
      'subject': subject.trim(),
      'description': description.trim(),
      for (var i = 0; i < contexts.length; i++) ...{
        'contexts[$i][type]': contexts[i]['type'],
        'contexts[$i][id]': contexts[i]['id'],
      },
      if (attachmentPaths.isNotEmpty)
        'attachments': [for (final path in attachmentPaths.take(4)) await MultipartFile.fromFile(path, filename: path.split(Platform.pathSeparator).last)],
    });
    final response = await ApiClient.dio.post<dynamic>('/mobile/client/support/tickets', data: form, options: Options(contentType: 'multipart/form-data'));
    ApiClient.ensureSuccess(response);
  }

  Future<SupportTicketDetail> ticket(int id) async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/client/support/tickets/$id');
    ApiClient.ensureSuccess(response);
    return SupportTicketDetail.fromJson(_data(response.data));
  }

  Future<void> replyTicket(int id, String message, {List<String> attachmentPaths = const []}) async {
    final form = FormData.fromMap({
      if (message.trim().isNotEmpty) 'message': message.trim(),
      if (attachmentPaths.isNotEmpty)
        'attachments': [for (final path in attachmentPaths.take(4)) await MultipartFile.fromFile(path, filename: path.split(Platform.pathSeparator).last)],
    });
    final response = await ApiClient.dio.post<dynamic>('/mobile/client/support/tickets/$id/messages', data: form, options: Options(contentType: 'multipart/form-data'));
    ApiClient.ensureSuccess(response);
  }

  Future<File> downloadAttachment(SupportAttachment attachment) async {
    if (attachment.downloadEndpoint.isEmpty) throw const OvanieApiException('Cette pièce jointe n’est pas téléchargeable depuis l’API sécurisée.');
    final response = await ApiClient.dio.get<List<int>>(
      attachment.downloadEndpoint,
      options: Options(responseType: ResponseType.bytes, receiveTimeout: const Duration(seconds: 35), headers: const {'Accept': '*/*'}),
    );
    ApiClient.ensureSuccess(response);
    final bytes = response.data;
    if (bytes == null || bytes.isEmpty) throw const OvanieApiException('La pièce jointe reçue est vide.');
    final directory = await getTemporaryDirectory();
    final safe = attachment.name.replaceAll(RegExp(r'[^A-Za-z0-9._-]+'), '-');
    final file = File('${directory.path}/${safe.isEmpty ? 'piece-jointe' : safe}');
    await file.writeAsBytes(bytes, flush: true);
    return file;
  }

  Future<SupportConversationDetail> conversation(String token) async {
    final response = await ApiClient.dio.get<dynamic>('/support/chat/$token');
    ApiClient.ensureSuccess(response);
    if (response.data is! Map) throw const OvanieApiException('Conversation support indisponible.');
    return SupportConversationDetail.fromJson(Map<String, dynamic>.from(response.data as Map));
  }

  Future<void> sendConversationMessage(String token, String message) async {
    final response = await ApiClient.dio.post<dynamic>('/support/chat/$token/messages', data: {'message': message.trim()});
    ApiClient.ensureSuccess(response);
  }

  Future<String> startConversation({required String subject, required String message}) async {
    final response = await ApiClient.dio.post<dynamic>('/support/chat/start', data: {'channel': 'chat', 'service': 'client', 'subject': subject.trim(), 'message': message.trim()});
    ApiClient.ensureSuccess(response);
    final body = response.data;
    final token = body is Map ? '${body['conversation_token'] ?? ''}'.trim() : '';
    if (token.isEmpty) throw const OvanieApiException('La conversation n’a pas pu être créée.');
    return token;
  }
}
