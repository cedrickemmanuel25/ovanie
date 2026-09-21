import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/login_screen.dart';
import '../data/reviews_repository.dart';
import '../domain/review_models.dart';

class ReviewsScreen extends StatefulWidget {
  final VoidCallback? onOpenCart;
  final VoidCallback? onStartShopping;
  const ReviewsScreen({super.key, this.onOpenCart, this.onStartShopping});

  @override
  State<ReviewsScreen> createState() => _ReviewsScreenState();
}

class _ReviewsScreenState extends State<ReviewsScreen> with SingleTickerProviderStateMixin {
  final _repository = const ReviewsRepository();
  late final TabController _tabs;
  ReviewCenterData? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    if (SessionStore.instance.isAuthenticated) _load(); else _loading = false;
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await _repository.load();
      if (!mounted) return;
      setState(() {
        _data = data;
        _loading = false;
        _error = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  Future<void> _login() async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const LoginScreen()));
    if (SessionStore.instance.isAuthenticated) {
      setState(() => _loading = true);
      await _load();
    }
  }

  Future<void> _editProduct(ProductReviewItem item) async {
    final result = await _reviewDialog(
      title: item.productName,
      subtitle: 'Commande ${item.orderNumber}',
      initial: item.review,
    );
    if (result == null) return;
    try {
      await _repository.saveProduct(orderItemId: item.orderItemId, rating: result.$1, comment: result.$2);
      await _load();
      _message('Votre avis produit a été enregistré.');
    } catch (error) {
      _message(ApiClient.friendlyError(error));
    }
  }

  Future<void> _editDelivery(DeliveryReviewItem item) async {
    final result = await _reviewDialog(
      title: 'Expérience de livraison',
      subtitle: 'Commande ${item.orderNumber}',
      initial: item.review,
    );
    if (result == null) return;
    try {
      await _repository.saveDelivery(orderId: item.orderId, rating: result.$1, comment: result.$2);
      await _load();
      _message('Votre évaluation de la livraison a été enregistrée.');
    } catch (error) {
      _message(ApiClient.friendlyError(error));
    }
  }

  Future<(int, String)?> _reviewDialog({required String title, required String subtitle, ExistingReview? initial}) async {
    var rating = initial?.rating ?? 5;
    final comment = TextEditingController(text: initial?.comment ?? '');
    final result = await showDialog<(int, String)>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(title),
          content: SingleChildScrollView(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
              Text(subtitle, style: const TextStyle(color: OvanieColors.muted)),
              const SizedBox(height: 14),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(5, (index) {
                  final value = index + 1;
                  return IconButton(
                    onPressed: () => setDialogState(() => rating = value),
                    icon: Icon(value <= rating ? Icons.star_rounded : Icons.star_border_rounded, color: OvanieColors.warning, size: 32),
                  );
                }),
              ),
              const SizedBox(height: 10),
              TextField(controller: comment, minLines: 3, maxLines: 6, maxLength: 2000, decoration: const InputDecoration(labelText: 'Commentaire (facultatif)')),
            ]),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Annuler')),
            FilledButton(onPressed: () => Navigator.pop(context, (rating, comment.text.trim())), child: const Text('Enregistrer')),
          ],
        ),
      ),
    );
    comment.dispose();
    return result;
  }

  void _message(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  @override
  Widget build(BuildContext context) {
    if (!SessionStore.instance.isAuthenticated) {
      return Scaffold(
        appBar: AppBar(title: const Text('Notes et avis')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.rate_review_outlined, size: 52, color: OvanieColors.blue),
              const SizedBox(height: 14),
              const Text('Connectez-vous pour évaluer vos achats livrés.', textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(onPressed: _login, child: const Text('Se connecter')),
            ]),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notes et avis'),
        bottom: TabBar(controller: _tabs, tabs: const [Tab(text: 'Produits'), Tab(text: 'Livraison')]),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!, textAlign: TextAlign.center)))
              : TabBarView(
                  controller: _tabs,
                  children: [
                    _ProductReviews(items: _data?.products ?? const [], onEdit: _editProduct),
                    _DeliveryReviews(
                      items: _data?.deliveryRating == true ? (_data?.deliveries ?? const []) : const [],
                      onEdit: _editDelivery,
                    ),
                  ],
                ),
    );
  }
}

class _ProductReviews extends StatelessWidget {
  final List<ProductReviewItem> items;
  final ValueChanged<ProductReviewItem> onEdit;
  const _ProductReviews({required this.items, required this.onEdit});
  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const Center(child: Padding(padding: EdgeInsets.all(24), child: Text('Aucun produit livré à évaluer pour le moment.', textAlign: TextAlign.center)));
    return RefreshIndicator(
      onRefresh: () async {},
      child: ListView.builder(
        padding: const EdgeInsets.all(12),
        itemCount: items.length,
        itemBuilder: (context, index) {
          final item = items[index];
          return Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(children: [
                Container(
                  width: 62,
                  height: 62,
                  decoration: BoxDecoration(color: OvanieColors.background, borderRadius: BorderRadius.circular(12)),
                  child: item.productImageUrl.isEmpty
                      ? const Icon(Icons.inventory_2_outlined)
                      : ClipRRect(borderRadius: BorderRadius.circular(12), child: Image.network(item.productImageUrl, fit: BoxFit.contain, errorBuilder: (_, __, ___) => const Icon(Icons.inventory_2_outlined))),
                ),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(item.productName, style: const TextStyle(fontWeight: FontWeight.w900)),
                  Text('Commande ${item.orderNumber}', style: const TextStyle(color: OvanieColors.muted, fontSize: 12)),
                  const SizedBox(height: 6),
                  _RatingDisplay(rating: item.review?.rating),
                ])),
                TextButton(onPressed: () => onEdit(item), child: Text(item.review == null ? 'Évaluer' : 'Modifier')),
              ]),
            ),
          );
        },
      ),
    );
  }
}

class _DeliveryReviews extends StatelessWidget {
  final List<DeliveryReviewItem> items;
  final ValueChanged<DeliveryReviewItem> onEdit;
  const _DeliveryReviews({required this.items, required this.onEdit});
  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const Center(child: Padding(padding: EdgeInsets.all(24), child: Text('Aucune livraison terminée à évaluer pour le moment.', textAlign: TextAlign.center)));
    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: items.length,
      itemBuilder: (context, index) {
        final item = items[index];
        return Card(
          margin: const EdgeInsets.only(bottom: 10),
          child: ListTile(
            leading: const CircleAvatar(child: Icon(Icons.local_shipping_outlined)),
            title: Text('Commande ${item.orderNumber}', style: const TextStyle(fontWeight: FontWeight.w900)),
            subtitle: _RatingDisplay(rating: item.review?.rating),
            trailing: TextButton(onPressed: () => onEdit(item), child: Text(item.review == null ? 'Évaluer' : 'Modifier')),
          ),
        );
      },
    );
  }
}

class _RatingDisplay extends StatelessWidget {
  final int? rating;
  const _RatingDisplay({this.rating});
  @override
  Widget build(BuildContext context) => Row(
        mainAxisSize: MainAxisSize.min,
        children: List.generate(5, (index) => Icon(index < (rating ?? 0) ? Icons.star_rounded : Icons.star_border_rounded, color: OvanieColors.warning, size: 18)),
      );
}
