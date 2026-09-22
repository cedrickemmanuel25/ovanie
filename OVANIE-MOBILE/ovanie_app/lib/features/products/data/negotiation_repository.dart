import '../../../core/network/api_client.dart';

/// Les 3 offres réelles définies par le vendeur pour un produit négociable
/// (price_p1/p2/p3 côté Laravel), du plus proche du prix affiché au plus
/// avantageux. Jamais transmises à un visiteur non connecté : voir
/// NegotiationController::offers() (routes/api.php, middleware auth:sanctum).
class NegotiationOffers {
  final List<int> amounts;
  final int finalOfferTtlSeconds;

  const NegotiationOffers({
    required this.amounts,
    required this.finalOfferTtlSeconds,
  });
}

class NegotiationAcceptResult {
  final bool accepted;
  final int? negotiationId;
  final String message;

  const NegotiationAcceptResult({
    required this.accepted,
    required this.negotiationId,
    required this.message,
  });
}

/// Négociation guidée en 3 paliers, identique au parcours Web ("Négocier"
/// sur la fiche produit) : le client connecté découvre les offres une par
/// une et choisit "Ajouter au panier à ce prix" ou passe à l'offre
/// suivante. Le serveur revalide toujours le montant choisi contre les
/// vrais seuils vendeur avant d'accepter une négociation.
class NegotiationRepository {
  const NegotiationRepository();

  Future<NegotiationOffers> getOffers(String productSlug) async {
    final slug = productSlug.trim();
    final response = await ApiClient.dio.get('/products/$slug/negotiation-offers');
    ApiClient.ensureSuccess(response);

    final data = response.data;
    final map = data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
    final rawOffers = map['offers'];
    final amounts = rawOffers is List
        ? rawOffers
            .map((value) => value is num ? value.round() : int.tryParse('$value') ?? 0)
            .where((value) => value > 0)
            .toList(growable: false)
        : const <int>[];

    if (amounts.isEmpty) {
      throw const OvanieApiException('Aucune offre disponible pour ce produit.');
    }

    final ttl = map['final_offer_ttl_seconds'] is num
        ? (map['final_offer_ttl_seconds'] as num).toInt()
        : 120;

    return NegotiationOffers(amounts: amounts, finalOfferTtlSeconds: ttl);
  }

  Future<NegotiationAcceptResult> acceptOffer(String productSlug, num proposedPrice) async {
    final slug = productSlug.trim();
    final response = await ApiClient.dio.post(
      '/products/$slug/negotiation-offers/accept',
      data: {'proposed_price': proposedPrice},
    );

    final status = response.statusCode ?? 0;
    final data = response.data;
    if (data is! Map || status == 401 || status == 403 || status >= 500) {
      ApiClient.ensureSuccess(response);
      throw const OvanieApiException('Réponse inattendue du serveur.');
    }

    final map = Map<String, dynamic>.from(data);
    return NegotiationAcceptResult(
      accepted: map['accepted'] == true,
      negotiationId: map['negotiation_id'] is num ? (map['negotiation_id'] as num).toInt() : null,
      message: map['message']?.toString() ?? '',
    );
  }

  Future<String> addNegotiatedToCart({
    required int productId,
    required num negotiatedPrice,
    required int negotiationId,
    required int quantity,
  }) async {
    final response = await ApiClient.dio.post('/cart/add-negotiated', data: {
      'product_id': productId,
      'negotiated_price': negotiatedPrice,
      'negotiation_id': negotiationId,
      'quantity': quantity,
    });

    final data = response.data;
    final map = data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
    if (map['success'] != true) {
      ApiClient.ensureSuccess(response);
      throw OvanieApiException(
        map['message']?.toString() ?? 'Impossible d’ajouter ce produit au panier.',
      );
    }

    return map['message']?.toString() ?? 'Produit ajouté au panier à ce prix !';
  }
}
