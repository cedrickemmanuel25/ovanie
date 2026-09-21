import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/login_screen.dart';
import '../../auth/presentation/register_screen.dart';
import '../../catalog/presentation/catalog_screen.dart';
import '../../checkout/presentation/checkout_screen.dart';
import '../../favorites/presentation/favorites_screen.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../data/cart_api_repository.dart';
import '../domain/cart_store.dart';

class CartScreen extends StatefulWidget {
  final VoidCallback? onBack;
  final VoidCallback? onStartShopping;
  final VoidCallback? onOpenFavorites;

  const CartScreen({
    super.key,
    this.onBack,
    this.onStartShopping,
    this.onOpenFavorites,
  });

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  Set<int>? _selectedIds;
  bool _openingCheckout = false;
  bool _refreshingFromServer = false;
  Timer? _serverRefreshTimer;

  @override
  void initState() {
    super.initState();
    if (SessionStore.instance.isAuthenticated) {
      unawaited(_refreshFromServer());
    }
    _serverRefreshTimer = Timer.periodic(
      const Duration(seconds: 5),
      (_) {
        if (MainNavigationStore.instance.currentTab == OvanieMainTab.cart) {
          unawaited(_refreshFromServer());
        }
      },
    );
  }

  Future<void> _refreshFromServer() async {
    if (_refreshingFromServer || !SessionStore.instance.isAuthenticated) return;
    _refreshingFromServer = true;
    try {
      await const CartApiRepository().refreshLocalCartFromServer();
    } catch (_) {
      // Le miroir local reste affiché si le serveur est momentanément indisponible.
    } finally {
      _refreshingFromServer = false;
    }
  }

  @override
  void dispose() {
    _serverRefreshTimer?.cancel();
    super.dispose();
  }

  void _handleBack() {
    if (widget.onBack != null) {
      widget.onBack!();
      return;
    }
    if (Navigator.of(context).canPop()) {
      Navigator.of(context).pop();
    }
  }

  void _startShopping() {
    if (widget.onStartShopping != null) {
      widget.onStartShopping!();
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const CatalogScreen()),
    );
  }

  void _openFavorites() {
    if (widget.onOpenFavorites != null) {
      widget.onOpenFavorites!();
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const FavoritesScreen()),
    );
  }

  Set<int> _effectiveSelection(CartStore cart) {
    final available = cart.lines.map((line) => line.product.id).toSet();
    if (_selectedIds == null) return available;
    return _selectedIds!.where(available.contains).toSet();
  }

  void _toggleSelection(CartStore cart, int productId) {
    final current = _effectiveSelection(cart);
    setState(() {
      _selectedIds = current;
      if (_selectedIds!.contains(productId)) {
        _selectedIds!.remove(productId);
      } else {
        _selectedIds!.add(productId);
      }
    });
  }

  void _toggleSelectAll(CartStore cart) {
    final all = cart.lines.map((line) => line.product.id).toSet();
    final current = _effectiveSelection(cart);
    setState(() {
      _selectedIds = current.length == all.length ? <int>{} : all;
    });
  }

  Future<void> _removeSelected(CartStore cart) async {
    final selected = _effectiveSelection(cart).toList(growable: false);
    if (selected.isEmpty) return;

    for (final productId in selected) {
      CartStore.instance.remove(productId);
    }
    await CartStore.instance.flush();
    if (SessionStore.instance.isAuthenticated) {
      try {
        await CartStore.instance.flushPendingServerMutations();
      } catch (_) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'La suppression a été enregistrée sur ce téléphone. La synchronisation sera réessayée automatiquement.',
            ),
          ),
        );
      }
    }
    if (mounted) setState(() => _selectedIds = null);
  }

  Future<void> _startCheckout() async {
    if (_openingCheckout) return;

    final cart = CartStore.instance;
    final selection = _effectiveSelection(cart);
    if (selection.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Sélectionnez au moins un produit avant de passer la commande.')),
      );
      return;
    }

    final unavailable = cart.lines.where(
      (line) => selection.contains(line.product.id),
    ).where(
      (line) => !line.product.canAddToCart ||
          line.product.stock <= 0 ||
          line.quantity > line.product.stock ||
          line.quantity < (line.product.minOrderQuantity <= 0 ? 1 : line.product.minOrderQuantity),
    );
    if (unavailable.isNotEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Un produit du panier n’est plus disponible dans la quantité demandée.',
          ),
        ),
      );
      return;
    }

    if (!SessionStore.instance.isAuthenticated) {
      final action = await _showAuthenticationRequired();
      if (!mounted || action == null) return;

      final bool? connected;
      if (action == _CheckoutAuthAction.login) {
        connected = await Navigator.of(context).push<bool>(
          MaterialPageRoute<bool>(builder: (_) => const LoginScreen()),
        );
      } else {
        connected = await Navigator.of(context).push<bool>(
          MaterialPageRoute<bool>(builder: (_) => const RegisterScreen()),
        );
      }

      if (!mounted) return;
      if (connected != true && !SessionStore.instance.isAuthenticated) return;
    }

    setState(() => _openingCheckout = true);
    try {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => CheckoutScreen(
            selectedProductIds: selection.toList(growable: false),
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _openingCheckout = false);
    }
  }

  Future<_CheckoutAuthAction?> _showAuthenticationRequired() {
    return showModalBottomSheet<_CheckoutAuthAction>(
      context: context,
      backgroundColor: Colors.transparent,
      useSafeArea: true,
      builder: (sheetContext) {
        return Container(
          margin: const EdgeInsets.all(14),
          padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            boxShadow: const [
              BoxShadow(
                color: Color(0x24000000),
                blurRadius: 28,
                offset: Offset(0, 12),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(
                  color: OvanieColors.blue.withValues(alpha: .08),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.person_outline_rounded,
                  color: OvanieColors.blue,
                  size: 30,
                ),
              ),
              const SizedBox(height: 14),
              const Text(
                'Connectez-vous pour continuer',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: OvanieColors.text,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Votre compte permet de sécuriser la commande et de retrouver votre panier sur tous vos appareils.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: OvanieColors.muted,
                  fontSize: 12.5,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: FilledButton(
                  onPressed: () => Navigator.of(sheetContext).pop(
                    _CheckoutAuthAction.login,
                  ),
                  style: FilledButton.styleFrom(
                    backgroundColor: OvanieColors.orange,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text(
                    'Se connecter',
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: OutlinedButton(
                  onPressed: () => Navigator.of(sheetContext).pop(
                    _CheckoutAuthAction.register,
                  ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: OvanieColors.navy,
                    side: const BorderSide(color: OvanieColors.navy),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text(
                    'Créer un compte',
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        bottom: false,
        child: AnimatedBuilder(
          animation: CartStore.instance,
          builder: (context, _) {
            final cart = CartStore.instance;
            if (cart.lines.isEmpty) {
              return _EmptyCartView(
                onBack: _handleBack,
                onStartShopping: _startShopping,
                onOpenFavorites: _openFavorites,
              );
            }

            final selection = _effectiveSelection(cart);
            return _FilledCartView(
              cart: cart,
              selectedIds: selection,
              onBack: _handleBack,
              onOpenFavorites: _openFavorites,
              onToggleSelection: (productId) =>
                  _toggleSelection(cart, productId),
              onToggleAll: () => _toggleSelectAll(cart),
              onRemoveSelected: () => _removeSelected(cart),
              onCheckout: _openingCheckout ? null : _startCheckout,
              openingCheckout: _openingCheckout,
            );
          },
        ),
      ),
    );
  }
}

enum _CheckoutAuthAction { login, register }

class _FilledCartView extends StatelessWidget {
  final CartStore cart;
  final Set<int> selectedIds;
  final VoidCallback onBack;
  final VoidCallback onOpenFavorites;
  final ValueChanged<int> onToggleSelection;
  final VoidCallback onToggleAll;
  final VoidCallback onRemoveSelected;
  final VoidCallback? onCheckout;
  final bool openingCheckout;

  const _FilledCartView({
    required this.cart,
    required this.selectedIds,
    required this.onBack,
    required this.onOpenFavorites,
    required this.onToggleSelection,
    required this.onToggleAll,
    required this.onRemoveSelected,
    required this.onCheckout,
    required this.openingCheckout,
  });

  @override
  Widget build(BuildContext context) {
    final allSelected = selectedIds.length == cart.lines.length;
    final itemsCount = cart.itemsCount;
    final unavailable = cart.lines.where(
      (line) => !line.product.canAddToCart ||
          line.product.stock <= 0 ||
          line.quantity > line.product.stock,
    );
    final canCheckout = unavailable.isEmpty;

    return CustomScrollView(
      physics: const BouncingScrollPhysics(),
      slivers: [
        SliverToBoxAdapter(
          child: _CartHeader(
            count: itemsCount,
            onBack: onBack,
            onOpenFavorites: onOpenFavorites,
          ),
        ),
        const SliverToBoxAdapter(child: SizedBox(height: 6)),
        const SliverToBoxAdapter(child: _DeliveryAddressPreview()),
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(15),
                border: Border.all(color: OvanieColors.border),
              ),
              child: Column(
                children: [
                  for (var i = 0; i < cart.lines.length; i++) ...[
                    _CartLineTile(
                      line: cart.lines[i],
                      selected: selectedIds.contains(cart.lines[i].product.id),
                      onToggleSelection: () =>
                          onToggleSelection(cart.lines[i].product.id),
                    ),
                    if (i != cart.lines.length - 1)
                      const Divider(
                        height: 1,
                        thickness: 1,
                        indent: 18,
                        endIndent: 18,
                        color: OvanieColors.border,
                      ),
                  ],
                ],
              ),
            ),
          ),
        ),
        SliverToBoxAdapter(
          child: _SelectionBar(
            allSelected: allSelected,
            count: itemsCount,
            selectedCount: selectedIds.length,
            onToggleAll: onToggleAll,
            onRemoveSelected: onRemoveSelected,
          ),
        ),
        const SliverToBoxAdapter(child: _DeliveryInfoCard()),
        SliverToBoxAdapter(
          child: _OrderSummary(
            cart: cart,
            canCheckout: canCheckout,
            openingCheckout: openingCheckout,
            onCheckout: onCheckout,
          ),
        ),
        const SliverToBoxAdapter(child: SizedBox(height: 18)),
      ],
    );
  }
}

class _CartHeader extends StatelessWidget {
  final int count;
  final VoidCallback onBack;
  final VoidCallback onOpenFavorites;

  const _CartHeader({
    required this.count,
    required this.onBack,
    required this.onOpenFavorites,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 18, 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Panier',
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 28,
                    height: 1.05,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '$count article${count > 1 ? 's' : ''}',
                  style: const TextStyle(
                    color: OvanieColors.muted,
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onOpenFavorites,
            tooltip: 'Favoris',
            icon: const Icon(
              Icons.favorite_border_rounded,
              color: OvanieColors.navy,
              size: 29,
            ),
          ),
          Stack(
            clipBehavior: Clip.none,
            children: [
              IconButton(
                onPressed: null,
                tooltip: 'Panier',
                icon: const Icon(
                  Icons.shopping_cart_outlined,
                  color: OvanieColors.navy,
                  size: 29,
                ),
              ),
              if (count > 0)
                Positioned(
                  right: 0,
                  top: 0,
                  child: Container(
                    constraints: const BoxConstraints(minWidth: 20, minHeight: 20),
                    padding: const EdgeInsets.symmetric(horizontal: 5),
                    alignment: Alignment.center,
                    decoration: const BoxDecoration(
                      color: OvanieColors.orange,
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      '$count',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DeliveryAddressPreview extends StatelessWidget {
  const _DeliveryAddressPreview();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF5EF),
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Row(
        children: [
          Icon(
            Icons.location_on_outlined,
            color: OvanieColors.orange,
            size: 25,
          ),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Livraison en Côte d’Ivoire',
                  style: TextStyle(
                    color: OvanieColors.text,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(height: 3),
                Text(
                  'Adresse à renseigner lors de la commande',
                  style: TextStyle(
                    color: OvanieColors.orange,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CartLineTile extends StatelessWidget {
  final CartLine line;
  final bool selected;
  final VoidCallback onToggleSelection;

  const _CartLineTile({
    required this.line,
    required this.selected,
    required this.onToggleSelection,
  });

  String _subtitle() {
    final product = line.product;
    if (product.shortDescription.trim().isNotEmpty) {
      return product.shortDescription.trim();
    }
    if (product.weightKg > 0) {
      return '${ProductModelHelper.compact(product.weightKg)} kg';
    }
    if (product.dimensionsLabel.isNotEmpty) return product.dimensionsLabel;
    if (product.unitLabel.trim().isNotEmpty) return product.unitLabel.trim();
    return '';
  }

  @override
  Widget build(BuildContext context) {
    final product = line.product;
    final hasOldPrice = product.hasDiscount && product.price > line.unitPrice;
    final inStock = product.canAddToCart && product.stock >= line.quantity;

    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 390;
        final imageSize = compact ? 76.0 : 92.0;
        final actionWidth = compact ? 132.0 : 142.0;

        return Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 10, 12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              _SelectionCheckbox(
                selected: selected,
                onTap: onToggleSelection,
              ),
              const SizedBox(width: 8),
              SizedBox(
                width: imageSize,
                height: imageSize,
                child: _ProductImage(url: product.imageUrl),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: OvanieColors.text,
                        fontSize: compact ? 12.4 : 13.5,
                        height: 1.2,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (_subtitle().isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        _subtitle(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: OvanieColors.muted,
                          fontSize: 11.5,
                        ),
                      ),
                    ],
                    const SizedBox(height: 7),
                    Text(
                      inStock ? 'En stock' : 'Stock insuffisant',
                      style: TextStyle(
                        color: inStock ? OvanieColors.success : OvanieColors.danger,
                        fontSize: 11.2,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 9),
                    Wrap(
                      spacing: 8,
                      runSpacing: 3,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          formatFcfa(line.unitPrice),
                          style: const TextStyle(
                            color: OvanieColors.orange,
                            fontSize: 13.2,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        if (hasOldPrice)
                          Text(
                            formatFcfa(product.price),
                            style: const TextStyle(
                              color: OvanieColors.muted,
                              fontSize: 10.5,
                              decoration: TextDecoration.lineThrough,
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              SizedBox(
                width: actionWidth,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Expanded(
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            alignment: Alignment.centerRight,
                            child: Text(
                              formatFcfa(line.subtotal),
                              maxLines: 1,
                              softWrap: false,
                              style: TextStyle(
                                color: OvanieColors.orange,
                                fontSize: compact ? 13.2 : 14.2,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 4),
                        IconButton(
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(
                            minWidth: 30,
                            minHeight: 30,
                          ),
                          tooltip: 'Supprimer',
                          onPressed: () async {
                            final synced = await CartStore.instance
                                .removeAndSync(product.id);
                            if (!context.mounted ||
                                synced ||
                                !SessionStore.instance.isAuthenticated) {
                              return;
                            }
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text(
                                  'Le produit a été supprimé sur ce téléphone. La synchronisation sera réessayée automatiquement.',
                                ),
                              ),
                            );
                          },
                          icon: const Icon(
                            Icons.delete_outline_rounded,
                            color: OvanieColors.navy,
                            size: 20,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 28),
                    _QuantityControl(line: line),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class ProductModelHelper {
  static String compact(double value) {
    if (value == value.roundToDouble()) return value.round().toString();
    return value.toStringAsFixed(1);
  }
}

class _ProductImage extends StatelessWidget {
  final String url;

  const _ProductImage({required this.url});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
      ),
      child: url.trim().isEmpty
          ? const Icon(
              Icons.inventory_2_outlined,
              color: Color(0xFFB9C2D2),
              size: 42,
            )
          : Image.network(
              url,
              fit: BoxFit.contain,
              cacheWidth: 260,
              cacheHeight: 260,
              errorBuilder: (_, __, ___) => const Icon(
                Icons.broken_image_outlined,
                color: Color(0xFFB9C2D2),
                size: 40,
              ),
            ),
    );
  }
}

class _SelectionCheckbox extends StatelessWidget {
  final bool selected;
  final VoidCallback onTap;

  const _SelectionCheckbox({required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(7),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 140),
        width: 24,
        height: 24,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? OvanieColors.orange : Colors.white,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(
            color: selected ? OvanieColors.orange : OvanieColors.border,
            width: 1.2,
          ),
        ),
        child: selected
            ? const Icon(Icons.check_rounded, color: Colors.white, size: 18)
            : null,
      ),
    );
  }
}

class _QuantityControl extends StatelessWidget {
  final CartLine line;

  const _QuantityControl({required this.line});

  @override
  Widget build(BuildContext context) {
    final product = line.product;
    final canIncrement = product.canAddToCart && line.quantity < product.stock;
    return Container(
      height: 42,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: OvanieColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: _QtyTap(
              icon: Icons.remove_rounded,
              onTap: () => CartStore.instance.decrement(product.id),
            ),
          ),
          Expanded(
            child: Center(
              child: Text(
                '${line.quantity}',
                style: const TextStyle(
                  color: OvanieColors.text,
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ),
          Expanded(
            child: _QtyTap(
              icon: Icons.add_rounded,
              onTap: canIncrement
                  ? () => CartStore.instance.increment(product.id)
                  : null,
            ),
          ),
        ],
      ),
    );
  }
}

class _QtyTap extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;

  const _QtyTap({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: SizedBox.expand(
        child: Icon(
          icon,
          size: 19,
          color: onTap == null ? OvanieColors.border : OvanieColors.navy,
        ),
      ),
    );
  }
}

class _SelectionBar extends StatelessWidget {
  final bool allSelected;
  final int count;
  final int selectedCount;
  final VoidCallback onToggleAll;
  final VoidCallback onRemoveSelected;

  const _SelectionBar({
    required this.allSelected,
    required this.count,
    required this.selectedCount,
    required this.onToggleAll,
    required this.onRemoveSelected,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 10, 16, 0),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: OvanieColors.border),
      ),
      child: Row(
        children: [
          _SelectionCheckbox(selected: allSelected, onTap: onToggleAll),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Tout sélectionner ($count)',
              style: const TextStyle(
                color: OvanieColors.text,
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          TextButton.icon(
            onPressed: selectedCount == 0 ? null : onRemoveSelected,
            icon: const Icon(Icons.delete_outline_rounded, size: 19),
            label: const Text('Supprimer la sélection'),
            style: TextButton.styleFrom(
              foregroundColor: OvanieColors.danger,
              padding: const EdgeInsets.symmetric(horizontal: 4),
              textStyle: const TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DeliveryInfoCard extends StatelessWidget {
  const _DeliveryInfoCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 10, 16, 0),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: OvanieColors.border),
      ),
      child: const Row(
        children: [
          Icon(
            Icons.local_shipping_outlined,
            color: OvanieColors.navy,
            size: 30,
          ),
          SizedBox(width: 13),
          Expanded(
            flex: 3,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Livraison estimée',
                  style: TextStyle(
                    color: OvanieColors.text,
                    fontSize: 12.8,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(height: 3),
                Text(
                  'Délai communiqué après le choix de l’adresse',
                  style: TextStyle(
                    color: OvanieColors.text,
                    fontSize: 11.2,
                    height: 1.35,
                  ),
                ),
                SizedBox(height: 5),
                Text(
                  'Options adaptées à votre destination',
                  style: TextStyle(
                    color: OvanieColors.orange,
                    fontSize: 11.2,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: 8),
          Expanded(
            flex: 2,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                DecoratedBox(
                  decoration: BoxDecoration(
                    color: Color(0xFFE8F8EF),
                    borderRadius: BorderRadius.all(Radius.circular(5)),
                  ),
                  child: Padding(
                    padding: EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                    child: Text(
                      'Livraison disponible',
                      style: TextStyle(
                        color: OvanieColors.success,
                        fontSize: 10.2,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
                SizedBox(height: 8),
                Text(
                  'Frais confirmés avant validation',
                  style: TextStyle(
                    color: OvanieColors.muted,
                    fontSize: 10.5,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _OrderSummary extends StatelessWidget {
  final CartStore cart;
  final bool canCheckout;
  final bool openingCheckout;
  final VoidCallback? onCheckout;

  const _OrderSummary({
    required this.cart,
    required this.canCheckout,
    required this.openingCheckout,
    required this.onCheckout,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: OvanieColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Récapitulatif de la commande',
                  style: TextStyle(
                    color: OvanieColors.text,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 13),
                _SummaryLine(
                  label:
                      'Sous-total (${cart.itemsCount} article${cart.itemsCount > 1 ? 's' : ''})',
                  value: formatFcfa(cart.subtotal),
                ),
                const SizedBox(height: 10),
                const _SummaryLine(
                  label: 'Livraison',
                  value: 'À confirmer',
                ),
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 10),
                  child: Divider(height: 1, color: OvanieColors.border),
                ),
                _SummaryLine(
                  label: 'Total à payer',
                  value: formatFcfa(cart.subtotal),
                  emphasis: true,
                ),
                if (!canCheckout) ...[
                  const SizedBox(height: 10),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF1F0),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text(
                      'Modifiez les quantités des produits indisponibles avant de continuer.',
                      style: TextStyle(
                        color: OvanieColors.danger,
                        fontSize: 10.8,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            height: 54,
            child: FilledButton.icon(
              onPressed: canCheckout ? onCheckout : null,
              icon: openingCheckout
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.lock_outline_rounded, size: 19),
              label: const Text(
                'Passer la commande',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w900),
              ),
              style: FilledButton.styleFrom(
                elevation: 0,
                backgroundColor: OvanieColors.orange,
                disabledBackgroundColor:
                    OvanieColors.orange.withValues(alpha: .55),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(9),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryLine extends StatelessWidget {
  final String label;
  final String value;
  final bool emphasis;

  const _SummaryLine({
    required this.label,
    required this.value,
    this.emphasis = false,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              color: OvanieColors.text,
              fontSize: emphasis ? 13.5 : 11.8,
              fontWeight: emphasis ? FontWeight.w900 : FontWeight.w500,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Text(
          value,
          style: TextStyle(
            color: emphasis ? OvanieColors.orange : OvanieColors.text,
            fontSize: emphasis ? 18 : 12.5,
            fontWeight: emphasis ? FontWeight.w900 : FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _EmptyCartView extends StatelessWidget {
  final VoidCallback onBack;
  final VoidCallback onStartShopping;
  final VoidCallback onOpenFavorites;

  const _EmptyCartView({
    required this.onBack,
    required this.onStartShopping,
    required this.onOpenFavorites,
  });

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const BouncingScrollPhysics(),
      slivers: [
        SliverToBoxAdapter(
          child: _CartHeader(
            count: 0,
            onBack: onBack,
            onOpenFavorites: onOpenFavorites,
          ),
        ),
        SliverFillRemaining(
          hasScrollBody: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(24, 16, 24, 32),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const SizedBox(height: 4),
                const _EmptyCartIllustration(),
                const SizedBox(height: 26),
                const Text(
                  'Votre panier est vide',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: OvanieColors.navy,
                    fontSize: 25,
                    height: 1.12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Ajoutez des matériaux, équipements et outils\npour démarrer votre commande.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: OvanieColors.muted,
                    fontSize: 15,
                    height: 1.45,
                  ),
                ),
                const SizedBox(height: 28),
                SizedBox(
                  width: 280,
                  height: 54,
                  child: FilledButton(
                    onPressed: onStartShopping,
                    style: FilledButton.styleFrom(
                      elevation: 0,
                      backgroundColor: OvanieColors.orange,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    child: const Text(
                      'Explorer le catalogue',
                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                TextButton(
                  onPressed: onOpenFavorites,
                  child: const Text(
                    'Voir mes favoris',
                    style: TextStyle(
                      color: OvanieColors.orange,
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(height: 30),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _EmptyCartIllustration extends StatelessWidget {
  const _EmptyCartIllustration();

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    return SizedBox(
      width: (width * .80).clamp(300.0, 390.0),
      child: AspectRatio(
        aspectRatio: 680 / 550,
        child: Image.asset(
          'assets/images/cart_empty_illustration.png',
          fit: BoxFit.contain,
          filterQuality: FilterQuality.high,
        ),
      ),
    );
  }
}
