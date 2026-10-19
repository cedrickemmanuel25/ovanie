import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/login_screen.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../data/reviews_repository.dart';
import '../domain/review_models.dart';

class ReviewsScreen extends StatefulWidget {
  final VoidCallback? onOpenCart;
  final VoidCallback? onStartShopping;
  const ReviewsScreen({super.key, this.onOpenCart, this.onStartShopping});

  @override
  State<ReviewsScreen> createState() => _ReviewsScreenState();
}

class _ReviewsScreenState extends State<ReviewsScreen> {
  final _repository = const ReviewsRepository();
  ReviewCenterData? _data;
  bool _loading = true;
  bool _deliveryTab = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (SessionStore.instance.isAuthenticated) {
      _load();
    } else {
      _loading = false;
    }
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
    final saved = await _openSheet(
      visual: OrderItemThumbnail(url: item.productImageUrl, width: 118, height: 118),
      title: item.productName,
      subtitle: 'Commande ${item.orderNumber}',
      initial: item.review,
      save: (rating, comment) =>
          _repository.saveProduct(orderItemId: item.orderItemId, rating: rating, comment: comment),
    );
    if (saved && mounted) {
      await _load();
      if (mounted) showOvanieToast(context, 'Votre avis produit a été enregistré.');
    }
  }

  Future<void> _editDelivery(DeliveryReviewItem item) async {
    final saved = await _openSheet(
      visual: const _TruckVisual(size: 118),
      title: 'Expérience de livraison',
      subtitle: 'Commande ${item.orderNumber}',
      initial: item.review,
      save: (rating, comment) => _repository.saveDelivery(orderId: item.orderId, rating: rating, comment: comment),
    );
    if (saved && mounted) {
      await _load();
      if (mounted) showOvanieToast(context, 'Votre évaluation de la livraison a été enregistrée.');
    }
  }

  Future<bool> _openSheet({
    required Widget visual,
    required String title,
    required String subtitle,
    required ExistingReview? initial,
    required Future<void> Function(int rating, String comment) save,
  }) async {
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (_) => _ReviewSheet(visual: visual, title: title, subtitle: subtitle, initial: initial, save: save),
    );
    return result == true;
  }

  @override
  Widget build(BuildContext context) {
    final authenticated = SessionStore.instance.isAuthenticated;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: const Color(0xFFF8F9FB),
        bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              SizedBox(
                height: 64,
                child: Row(
                  children: [
                    const SizedBox(width: 4),
                    IconButton(
                      onPressed: () => Navigator.of(context).maybePop(),
                      tooltip: 'Retour',
                      icon: const Icon(Icons.chevron_left_rounded, color: payNavy, size: 36),
                    ),
                    const Expanded(
                      child: Text(
                        'Notes et avis',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: payNavy, fontSize: 24, fontWeight: FontWeight.w800),
                      ),
                    ),
                    const SizedBox(width: 52),
                  ],
                ),
              ),
              if (authenticated)
                _SegmentedTabs(
                  left: 'Produits',
                  right: 'Livraison',
                  rightSelected: _deliveryTab,
                  onChanged: (value) => setState(() => _deliveryTab = value),
                ),
              Expanded(child: authenticated ? _buildBody() : _LoginPrompt(onLogin: _login)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator(color: OvanieColors.orange));
    if (_error != null && _data == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _error!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: payNavy, fontSize: 16),
              ),
              const SizedBox(height: 18),
              PaymentButton(label: 'Réessayer', onPressed: _load),
            ],
          ),
        ),
      );
    }
    final products = _data?.products ?? const <ProductReviewItem>[];
    final deliveries = _data?.deliveryRating == true ? (_data?.deliveries ?? const []) : const <DeliveryReviewItem>[];

    if (!_deliveryTab && products.isEmpty) {
      return const _EmptyReviews(
        image: 'assets/images/checkout/reviews_products_empty.png',
        text: 'Aucun produit livré à évaluer\npour le moment.',
      );
    }
    if (_deliveryTab && deliveries.isEmpty) {
      return const _EmptyReviews(
        image: 'assets/images/checkout/reviews_delivery_empty.png',
        text: 'Aucune livraison terminée à évaluer\npour le moment.',
      );
    }
    return RefreshIndicator(
      color: OvanieColors.orange,
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 20),
        children: [
          if (!_deliveryTab)
            for (final item in products)
              _ReviewCard(
                visual: OrderItemThumbnail(url: item.productImageUrl, width: 96, height: 96),
                title: item.productName,
                subtitle: 'Commande ${item.orderNumber}',
                rating: item.review?.rating ?? 0,
                onTap: () => _editProduct(item),
              )
          else
            for (final item in deliveries)
              _ReviewCard(
                visual: const _TruckVisual(size: 96),
                title: 'Expérience de livraison',
                subtitle: 'Commande ${item.orderNumber}',
                rating: item.review?.rating ?? 0,
                onTap: () => _editDelivery(item),
              ),
        ],
      ),
    );
  }
}

class _SegmentedTabs extends StatelessWidget {
  final String left;
  final String right;
  final bool rightSelected;
  final ValueChanged<bool> onChanged;

  const _SegmentedTabs({required this.left, required this.right, required this.rightSelected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    Widget tab(String label, bool selected, bool value) => Expanded(
      child: GestureDetector(
        onTap: () => onChanged(value),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          height: 50,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? Colors.white : Colors.transparent,
            borderRadius: BorderRadius.circular(12),
            boxShadow: selected
                ? const [BoxShadow(color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2))]
                : null,
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? payNavy : payMuted,
              fontSize: 17,
              fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ),
      ),
    );
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 4),
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(color: const Color(0xFFE9EDF2), borderRadius: BorderRadius.circular(16)),
      child: Row(children: [tab(left, !rightSelected, false), tab(right, rightSelected, true)]),
    );
  }
}

class _ReviewCard extends StatelessWidget {
  final Widget visual;
  final String title;
  final String subtitle;
  final int rating;
  final VoidCallback onTap;

  const _ReviewCard({
    required this.visual,
    required this.title,
    required this.subtitle,
    required this.rating,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 14),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: payBorder),
    ),
    child: Row(
      children: [
        visual,
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 3),
              Text(subtitle, style: const TextStyle(color: payMuted, fontSize: 14)),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: _Stars(rating: rating, size: 24),
                    ),
                  ),
                  const SizedBox(width: 8),
                  SizedBox(
                    width: 100,
                    height: 44,
                    child: rating > 0
                        ? OutlinedButton(
                            onPressed: onTap,
                            style: OutlinedButton.styleFrom(
                              foregroundColor: payNavy,
                              side: const BorderSide(color: payNavy, width: 1.4),
                              padding: EdgeInsets.zero,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: const Text('Modifier', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                          )
                        : FilledButton(
                            onPressed: onTap,
                            style: FilledButton.styleFrom(
                              elevation: 0,
                              backgroundColor: OvanieColors.orange,
                              foregroundColor: Colors.white,
                              padding: EdgeInsets.zero,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: const Text('Évaluer', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                          ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _Stars extends StatelessWidget {
  final int rating;
  final double size;
  final ValueChanged<int>? onChanged;

  const _Stars({required this.rating, this.size = 22, this.onChanged});

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: List.generate(5, (index) {
      final filled = index < rating;
      final icon = Icon(
        filled ? Icons.star_rounded : (onChanged == null ? Icons.star_rounded : Icons.star_border_rounded),
        color: filled
            ? const Color(0xFFFBBF24)
            : (onChanged == null ? const Color(0xFFD5DAE2) : const Color(0xFFB9C2D0)),
        size: size,
      );
      if (onChanged == null) return icon;
      return InkWell(
        customBorder: const CircleBorder(),
        onTap: () => onChanged!(index + 1),
        child: Padding(padding: const EdgeInsets.all(3), child: icon),
      );
    }),
  );
}

class _ReviewSheet extends StatefulWidget {
  final Widget visual;
  final String title;
  final String subtitle;
  final ExistingReview? initial;
  final Future<void> Function(int rating, String comment) save;

  const _ReviewSheet({
    required this.visual,
    required this.title,
    required this.subtitle,
    required this.initial,
    required this.save,
  });

  @override
  State<_ReviewSheet> createState() => _ReviewSheetState();
}

class _ReviewSheetState extends State<_ReviewSheet> {
  late int _rating = widget.initial?.rating ?? 0;
  late final TextEditingController _comment = TextEditingController(text: widget.initial?.comment ?? '');
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_rating == 0 || _saving) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.save(_rating, _comment.text.trim());
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 10, 20, 12 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 46,
              height: 5,
              decoration: BoxDecoration(color: const Color(0xFFD0D6DF), borderRadius: BorderRadius.circular(3)),
            ),
          ),
          const SizedBox(height: 18),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              widget.visual,
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 10),
                    Text(
                      widget.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: payNavy, fontSize: 20, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 3),
                    Text(widget.subtitle, style: const TextStyle(color: payMuted, fontSize: 15)),
                    const SizedBox(height: 10),
                    _Stars(rating: _rating, size: 38, onChanged: (value) => setState(() => _rating = value)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          const Text('Commentaire (facultatif)', style: TextStyle(color: payNavy, fontSize: 15.5)),
          const SizedBox(height: 8),
          TextField(
            controller: _comment,
            minLines: 2,
            maxLines: 5,
            maxLength: 2000,
            style: const TextStyle(color: payNavy, fontSize: 15.5),
            decoration: InputDecoration(
              hintText: 'Votre commentaire...',
              hintStyle: const TextStyle(color: Color(0xFF8A93A8)),
              counterText: '',
              contentPadding: const EdgeInsets.all(16),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Color(0xFFD5DCE6)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: OvanieColors.orange, width: 1.4),
              ),
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: const TextStyle(color: payRed, fontSize: 13.5)),
          ],
          if (_rating == 0) ...[
            const SizedBox(height: 8),
            const Text('Choisissez une note de 1 à 5 étoiles.', style: TextStyle(color: payMuted, fontSize: 13)),
          ],
          const SizedBox(height: 16),
          PaymentButton(label: 'Enregistrer', loading: _saving, onPressed: _rating == 0 ? null : _submit),
          Center(
            child: TextButton(
              onPressed: _saving ? null : () => Navigator.of(context).pop(false),
              child: const Text(
                'Annuler',
                style: TextStyle(color: payNavy, fontSize: 16.5, fontWeight: FontWeight.w600),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TruckVisual extends StatelessWidget {
  final double size;

  const _TruckVisual({required this.size});

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(10),
    child: Image.asset(
      'assets/images/checkout/review_delivery_truck.png',
      width: size,
      height: size,
      fit: BoxFit.cover,
    ),
  );
}

class _EmptyReviews extends StatelessWidget {
  final String image;
  final String text;

  const _EmptyReviews({required this.image, required this.text});

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    return ListView(
      padding: const EdgeInsets.fromLTRB(24, 50, 24, 24),
      children: [
        Center(
          child: Image.asset(image, width: (width * .72).clamp(220.0, 340.0), fit: BoxFit.contain),
        ),
        const SizedBox(height: 22),
        Text(
          text,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Color(0xFF4A556E), fontSize: 18, height: 1.45),
        ),
      ],
    );
  }
}

class _LoginPrompt extends StatelessWidget {
  final VoidCallback onLogin;

  const _LoginPrompt({required this.onLogin});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text(
            'Connectez-vous pour évaluer vos achats livrés.',
            textAlign: TextAlign.center,
            style: TextStyle(color: payNavy, fontSize: 16),
          ),
          const SizedBox(height: 18),
          PaymentButton(label: 'Se connecter', onPressed: onLogin),
        ],
      ),
    ),
  );
}
