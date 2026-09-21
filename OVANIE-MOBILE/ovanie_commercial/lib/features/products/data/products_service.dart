import 'package:dio/dio.dart';
import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../models/product_data.dart';

class ProductsService {
  ProductsService(this.api); final ApiClient api;
  Future<List<CommercialShopOption>> shops({String q=''}) async { final j=await api.getJson('/mobile/v1/commercial/products/shops',queryParameters:{'q':q}); return ((j['shops'] as List?) ?? const []).map((e)=>CommercialShopOption.fromJson(Map<String,dynamic>.from(e as Map))).toList(); }
  Future<ProductMetaData> meta() async {
    final payload = await api.getJson('/mobile/v1/reference-data');
    final rawData = payload['data'];
    if (rawData is! Map) {
      throw const ApiException(
        'Les référentiels OVANIE produit sont indisponibles.',
      );
    }
    final data = Map<String,dynamic>.from(rawData);
    final categories = data['product_categories'] ?? data['categories'];
    if (categories is! List || categories.isEmpty) {
      throw const ApiException(
        'Les catégories produit OVANIE sont indisponibles.',
      );
    }
    return ProductMetaData.fromJson(<String,dynamic>{
      'categories': categories,
    });
  }
  Future<List<CaptureSessionData>> sessions(int shopId) async { final j=await api.getJson('/mobile/v1/commercial/products/sessions',queryParameters:{'shop_id':shopId}); return ((j['sessions'] as List?) ?? const []).map((e)=>CaptureSessionData.fromJson(Map<String,dynamic>.from(e as Map))).toList(); }
  Future<CaptureSessionData> createSession({required int shopId,required String name,int? categoryId,int? subcategoryId,String notes='',bool keepCategory=true}) async { final j=await api.postJson('/mobile/v1/commercial/products/sessions',body:{'shop_id':shopId,'name':name,'category_id':categoryId,'subcategory_id':subcategoryId,'notes':notes,'keep_category':keepCategory}); return CaptureSessionData.fromJson(Map<String,dynamic>.from(j['session'] as Map)); }
  Future<Map<String,dynamic>> session(int id) => api.getJson('/mobile/v1/commercial/products/sessions/$id');
  Future<CapturedProductData> capture({required int sessionId,required String filePath,required int categoryId,int? subcategoryId}) async { final f=FormData.fromMap({'session_id':sessionId,'category_id':categoryId,'subcategory_id':subcategoryId,'photo':await MultipartFile.fromFile(filePath,filename:filePath.split('/').last)}); final j=await api.postFormData('/mobile/v1/commercial/products/capture',formData:f); return CapturedProductData.fromJson(Map<String,dynamic>.from(j['product'] as Map)); }
  Future<void> finishSession(int id) async { await api.postJson('/mobile/v1/commercial/products/sessions/$id/finish'); }
  Future<Map<String,dynamic>> incomplete(int sessionId) => api.getJson('/mobile/v1/commercial/products/incomplete',queryParameters:{'session_id':sessionId});
  Future<Map<String,dynamic>> product(int productId) => api.getJson('/mobile/v1/commercial/products/$productId/edit');
  Future<Map<String,dynamic>> saveStep(int productId,int step,Map<String,dynamic> data) => api.postJson('/mobile/v1/commercial/products/$productId/steps/$step',body:data);
  Future<Map<String,dynamic>> uploadMedia(int productId,String filePath,{String kind='gallery'}) async { final f=FormData.fromMap({'kind':kind,'media':await MultipartFile.fromFile(filePath,filename:filePath.split('/').last)}); return api.postFormData('/mobile/v1/commercial/products/$productId/media',formData:f); }
  Future<Map<String,dynamic>> publish(int productId)=>api.postJson('/mobile/v1/commercial/products/$productId/publish');
}
