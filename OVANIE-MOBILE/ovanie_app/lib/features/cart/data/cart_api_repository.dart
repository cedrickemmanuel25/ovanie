import '../../home/data/marketplace_repository.dart';
import '../../products/domain/product_model.dart';
import '../domain/cart_store.dart';
import 'cart_server_sync.dart';

/// Réconcilie le panier local et le panier Laravel après authentification.
///
/// Important : les mutations utilisateur en attente (suppression, quantité,
/// vidage) sont toujours appliquées AVANT la fusion. C'est ce qui empêche un
/// produit supprimé sur mobile de revenir depuis le panier web.
class CartApiRepository {
  const CartApiRepository();

  /// Synchronisation à exécuter immédiatement après login / inscription.
  ///
  /// - si le téléphone possède un panier invité, il est fusionné dans Laravel ;
  /// - si le téléphone est neuf ou vide, le panier existant du compte est
  ///   téléchargé depuis Laravel ;
  /// - après l'opération, le panier affiché localement est le miroir du panier
  ///   serveur commun au Web et à tous les appareils du même utilisateur.
  Future<void> syncAfterAuthentication() async {
    await mergeLocalCartIntoServer(refreshLocal: true);
  }

  Future<void> mergeLocalCartIntoServer({bool refreshLocal = false}) async {
    await CartStore.instance.flushPendingServerMutations();

    final revisionAtStart = CartStore.instance.localMutationRevision;
    final local = CartStore.instance.lines;
    const remote = CartServerSync();
    final initial = await remote.fetchItems();
    final byProduct = <String, ServerCartItem>{
      for (final item in initial) '${item.productId}:${item.variantId ?? 0}': item,
    };

    for (final line in local) {
      // L'utilisateur a modifié le panier pendant la fusion (ex. suppression) :
      // on arrête d'envoyer l'ancienne photo du panier.
      if (CartStore.instance.localMutationRevision != revisionAtStart) break;
      final existing = byProduct['${line.product.id}:${line.variant?.id ?? 0}'];
      if (existing == null) {
        await remote.setProductQuantity(line.product.id, line.quantity, productVariantId: line.variant?.id);
        continue;
      }

      // Lors de la première fusion d'un panier invité avec un compte existant,
      // conserver la quantité la plus élevée évite de perdre un panier web.
      final target = existing.quantity >= line.quantity
          ? existing.quantity
          : line.quantity;
      if (target != existing.quantity) {
        await remote.setProductQuantity(line.product.id, target, productVariantId: line.variant?.id);
      }
    }

    // Une requête de fusion partie juste avant une modification locale peut
    // arriver au serveur après elle et réintroduire un article supprimé :
    // on réécrit alors l'état local actuel, qui est l'intention la plus récente.
    if (CartStore.instance.localMutationRevision != revisionAtStart) {
      await CartStore.instance.flushPendingServerMutations();
      final current = <String, int>{};
      for (final line in CartStore.instance.lines) {
        final key = '${line.product.id}:${line.variant?.id ?? 0}';
        current[key] = (current[key] ?? 0) + line.quantity;
      }
      for (final line in local) {
        final key = '${line.product.id}:${line.variant?.id ?? 0}';
        await remote.setProductQuantity(line.product.id, current[key] ?? 0, productVariantId: line.variant?.id);
      }
    }

    if (refreshLocal) {
      await refreshLocalCartFromServer();
    }
  }

  Future<void> refreshLocalCartFromServer() async {
    await CartStore.instance.flushPendingServerMutations();
    final revisionBeforeRefresh = CartStore.instance.localMutationRevision;

    const remote = CartServerSync();
    final items = await remote.fetchItems();
    final marketplace = const MarketplaceRepository();
    final hydrated = <CartLine>[];

    for (final item in items) {
      if (item.productSlug.trim().isEmpty) continue;
      try {
        final product = await marketplace.getProductBySlug(item.productSlug);
        ProductVariantModel? variant;
        if (item.variantId != null) {
          for (final candidate in product.variants) {
            if (candidate.id == item.variantId) {
              variant = candidate;
              break;
            }
          }
        }
        hydrated.add(
          CartLine(
            product: product,
            quantity: item.quantity,
            unitPrice: item.unitPrice > 0 ? item.unitPrice : null,
            variant: variant,
          ),
        );
      } catch (_) {
        // Un produit supprimé/invisible ne doit pas empêcher les autres lignes
        // du panier serveur d'apparaître sur le nouvel appareil.
      }
    }

    // Une modification faite avec +, − ou supprimer pendant le chargement est
    // plus récente que la réponse reçue et ne doit jamais être écrasée.
    if (CartStore.instance.localMutationRevision == revisionBeforeRefresh) {
      CartStore.instance.replaceAll(hydrated);
    }
  }
}
