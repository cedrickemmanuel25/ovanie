import 'dart:io';

import 'package:dio/dio.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/network/api_client.dart';
import '../domain/order_model.dart';

class OrdersRepository {
  const OrdersRepository();

  Future<OrdersPage> fetchOrders({
    String bucket = 'in_progress',
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await ApiClient.dio.get<dynamic>(
      '/mobile/orders',
      queryParameters: <String, dynamic>{
        'bucket': bucket,
        'page': page,
        'per_page': perPage,
      },
    );
    ApiClient.ensureSuccess(response);

    final body = response.data;
    if (body is! Map) {
      throw const OvanieApiException('La liste de vos commandes est indisponible.');
    }

    final rawList = body['data'] is List ? body['data'] as List : const [];
    final meta = body['meta'] is Map
        ? Map<String, dynamic>.from(body['meta'] as Map)
        : <String, dynamic>{};
    final summaryMap = body['summary'] is Map
        ? Map<String, dynamic>.from(body['summary'] as Map)
        : <String, dynamic>{};

    return OrdersPage(
      orders: rawList
          .whereType<Map>()
          .map((raw) => MobileOrder.fromJson(Map<String, dynamic>.from(raw)))
          .where((order) => order.id > 0)
          .toList(growable: false),
      currentPage: int.tryParse('${meta['current_page'] ?? page}') ?? page,
      lastPage: int.tryParse('${meta['last_page'] ?? page}') ?? page,
      total: int.tryParse('${meta['total'] ?? rawList.length}') ?? rawList.length,
      summary: OrdersSummary.fromJson(summaryMap),
    );
  }

  Future<MobileOrderDetail> fetchOrderDetail(int orderId) async {
    final response = await ApiClient.dio.get<dynamic>('/mobile/orders/$orderId');
    ApiClient.ensureSuccess(response);

    final body = response.data;
    if (body is! Map) {
      throw const OvanieApiException('La commande OVANIE est indisponible.');
    }

    final data = body['data'] is Map
        ? Map<String, dynamic>.from(body['data'] as Map)
        : Map<String, dynamic>.from(body);
    final order = MobileOrder.fromJson(data);
    if (order.id <= 0) {
      throw const OvanieApiException('La commande OVANIE est invalide.');
    }

    final rawTimeline = body['timeline'] is List ? body['timeline'] as List : const [];
    return MobileOrderDetail(
      order: order,
      timeline: rawTimeline
          .whereType<Map>()
          .map((event) => OrderTimelineEvent.fromJson(Map<String, dynamic>.from(event)))
          .toList(growable: false),
    );
  }

  /// Conservé pour les écrans qui n'ont besoin que de la commande.
  Future<MobileOrder> fetchOrder(int orderId) async => (await fetchOrderDetail(orderId)).order;

  Future<MobileOrder> cancelOrder(int orderId) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/mobile/orders/$orderId/cancel',
      data: const <String, dynamic>{},
    );
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map || body['data'] is! Map) {
      throw const OvanieApiException('L’annulation de la commande n’a pas pu être enregistrée.');
    }
    return MobileOrder.fromJson(Map<String, dynamic>.from(body['data'] as Map));
  }

  Future<ReorderResult> reorder(int orderId) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/mobile/orders/$orderId/reorder',
      data: const <String, dynamic>{},
    );
    ApiClient.ensureSuccess(response);
    final body = response.data;
    if (body is! Map) {
      throw const OvanieApiException('Impossible de remettre les produits disponibles dans votre panier.');
    }
    final data = body['data'] is Map ? Map<String, dynamic>.from(body['data'] as Map) : <String, dynamic>{};
    return ReorderResult(
      addedLines: int.tryParse('${data['added_lines'] ?? 0}') ?? 0,
      skippedLines: int.tryParse('${data['skipped_lines'] ?? 0}') ?? 0,
      message: (body['message'] ?? 'Panier OVANIE mis à jour.').toString(),
    );
  }

  Future<MobileOrder> confirmReception(int orderId) async {
    final response = await ApiClient.dio.post<dynamic>(
      '/mobile/orders/$orderId/reception',
      data: const <String, dynamic>{},
    );
    ApiClient.ensureSuccess(response);

    final body = response.data;
    if (body is! Map || body['data'] is! Map) {
      throw const OvanieApiException('La confirmation de réception n’a pas pu être enregistrée.');
    }
    return MobileOrder.fromJson(Map<String, dynamic>.from(body['data'] as Map));
  }

  Future<File> downloadInvoice(MobileOrder order) async {
    if (order.id <= 0) {
      throw const OvanieApiException('Cette facture est indisponible.');
    }

    final response = await ApiClient.dio.get<List<int>>(
      '/mobile/orders/${order.id}/invoice',
      options: Options(
        responseType: ResponseType.bytes,
        receiveTimeout: const Duration(seconds: 35),
        headers: const <String, dynamic>{'Accept': 'application/pdf'},
      ),
    );
    ApiClient.ensureSuccess(response);

    final bytes = response.data;
    if (bytes == null || bytes.isEmpty) {
      throw const OvanieApiException('La facture reçue est vide.');
    }

    final directory = await getTemporaryDirectory();
    final safeInvoice = (order.invoiceNumber.isNotEmpty ? order.invoiceNumber : order.orderNumber)
        .replaceAll(RegExp(r'[^A-Za-z0-9_-]+'), '-');
    final file = File('${directory.path}/facture-$safeInvoice.pdf');
    await file.writeAsBytes(bytes, flush: true);
    return file;
  }
}


class ReorderResult {
  final int addedLines;
  final int skippedLines;
  final String message;

  const ReorderResult({required this.addedLines, required this.skippedLines, required this.message});
}
