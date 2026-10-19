import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

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

  const CartScreen({super.key, this.onBack, this.onStartShopping, this.onOpenFavorites});

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
    _serverRefreshTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (MainNavigationStore.instance.currentTab == OvanieMainTab.cart) {
        unawaited(_refreshFromServer());
      }
    });
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

  void _startShopping() {
    if (widget.onStartShopping != null) {
      widget.onStartShopping!();
      return;
    }
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const CatalogScreen()));
  }

  void _openFavorites() {
    if (widget.onOpenFavorites != null) {
      widget.onOpenFavorites!();
      return;
    }
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const FavoritesScreen()));
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
        // Le miroir local est déjà à jour ; la synchronisation reprendra.
      }
    }
    if (mounted) {
      showCartToast(
        context,
        selected.length > 1
            ? 'Les produits ont été retirés de votre panier.'
            : 'Le produit a été retiré de votre panier.',
      );
    }
    if (mounted) setState(() => _selectedIds = null);
  }

  Future<void> _startCheckout() async {
    if (_openingCheckout) return;

    final cart = CartStore.instance;
    final selection = _effectiveSelection(cart);
    if (selection.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Sélectionnez au moins un produit avant de passer la commande.')));
      return;
    }

    final unavailable = cart.lines
        .where((line) => selection.contains(line.product.id))
        .where(
          (line) =>
              !line.product.canAddToCart ||
              line.availableStock <= 0 ||
              line.quantity > line.availableStock ||
              line.quantity < (line.product.minOrderQuantity <= 0 ? 1 : line.product.minOrderQuantity),
        );
    if (unavailable.isNotEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Un produit du panier n’est plus disponible dans la quantité demandée.')),
      );
      return;
    }

    if (!SessionStore.instance.isAuthenticated) {
      final action = await _showAuthenticationRequired();
      if (!mounted || action == null) return;

      final bool? connected;
      if (action == _CheckoutAuthAction.login) {
        connected = await Navigator.of(
          context,
        ).push<bool>(MaterialPageRoute<bool>(builder: (_) => const LoginScreen()));
      } else {
        connected = await Navigator.of(
          context,
        ).push<bool>(MaterialPageRoute<bool>(builder: (_) => const RegisterScreen()));
      }

      if (!mounted) return;
      if (connected != true && !SessionStore.instance.isAuthenticated) return;
    }

    setState(() => _openingCheckout = true);
    try {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => CheckoutScreen(selectedProductIds: selection.toList(growable: false))),
      );
    } finally {
      if (mounted) setState(() => _openingCheckout = false);
    }
  }

  Future<_CheckoutAuthAction?> _showAuthenticationRequired() {
    return showModalBottomSheet<_CheckoutAuthAction>(
      context: context,
      backgroundColor: Colors.white,
      useSafeArea: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
      builder: (sheetContext) => _LoginRequiredSheet(
        onClose: () => Navigator.of(sheetContext).pop(),
        onLogin: () => Navigator.of(sheetContext).pop(_CheckoutAuthAction.login),
        onRegister: () => Navigator.of(sheetContext).pop(_CheckoutAuthAction.register),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    // Fond blanc : icônes de la barre d'état foncées, quelle que soit la page
    // précédente (l'accueil, par exemple, les met en blanc sur sa bannière).
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: _buildCart(context),
    );
  }

  Widget _buildCart(BuildContext context) {
    return AnimatedBuilder(
      animation: CartStore.instance,
      builder: (context, _) {
        final cart = CartStore.instance;
        if (cart.lines.isEmpty) {
          return Scaffold(
            backgroundColor: Colors.white,
            body: SafeArea(
              bottom: false,
              child: _EmptyCartView(onStartShopping: _startShopping, onOpenFavorites: _openFavorites),
            ),
          );
        }

        final selection = _effectiveSelection(cart);
        final selectedLines = cart.lines.where((line) => selection.contains(line.product.id));
        final total = selectedLines.fold<double>(0, (sum, line) => sum + line.subtotal);
        final blocked = selectedLines.any(_lineUnavailable);
        return Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            bottom: false,
            child: _FilledCartView(
              cart: cart,
              selectedIds: selection,
              onOpenFavorites: _openFavorites,
              onToggleSelection: (productId) => _toggleSelection(cart, productId),
              onToggleAll: () => _toggleSelectAll(cart),
              onRemoveSelected: () => _removeSelected(cart),
            ),
          ),
          bottomNavigationBar: _CheckoutBar(
            total: total,
            enabled: selection.isNotEmpty && !blocked,
            loading: _openingCheckout,
            onCheckout: _startCheckout,
          ),
        );
      },
    );
  }
}

enum _CheckoutAuthAction { login, register }

const Color _cartNavy = Color(0xFF0A1B4F);
const Color _cartMuted = Color(0xFF6B7591);
const Color _cartBorder = Color(0xFFE3E8F0);
const Color _cartSoft = Color(0xFFF1F4F8);
const Color _cartGreen = Color(0xFF16A34A);
const Color _cartRed = Color(0xFFE5231B);
const Color _cartRedSoft = Color(0xFFFFF1F1);

/// Vrai quand la ligne ne peut pas être commandée telle quelle.
bool _lineUnavailable(CartLine line) =>
    !line.product.canAddToCart || line.availableStock <= 0 || line.quantity > line.availableStock;

/// Message sombre flottant des maquettes (« Le produit a été retiré… »).
void showCartToast(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: const Color(0xFF23262F),
        elevation: 0,
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        duration: const Duration(seconds: 3),
        content: Row(
          children: [
            Container(
              width: 26,
              height: 26,
              decoration: const BoxDecoration(color: _cartGreen, shape: BoxShape.circle),
              child: const Icon(Icons.check_rounded, color: Colors.white, size: 18),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                message,
                style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ),
      ),
    );
}

class _FilledCartView extends StatelessWidget {
  final CartStore cart;
  final Set<int> selectedIds;
  final VoidCallback onOpenFavorites;
  final ValueChanged<int> onToggleSelection;
  final VoidCallback onToggleAll;
  final VoidCallback onRemoveSelected;

  const _FilledCartView({
    required this.cart,
    required this.selectedIds,
    required this.onOpenFavorites,
    required this.onToggleSelection,
    required this.onToggleAll,
    required this.onRemoveSelected,
  });

  @override
  Widget build(BuildContext context) {
    final allSelected = selectedIds.length == cart.lines.length;
    final itemsCount = cart.itemsCount;
    final blocked = cart.lines.any((line) => selectedIds.contains(line.product.id) && _lineUnavailable(line));
    final selectedLines = cart.lines.where((line) => selectedIds.contains(line.product.id)).toList();
    final selectedTotal = selectedLines.fold<double>(0, (sum, line) => sum + line.subtotal);

    return ListView(
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.only(bottom: 20),
      children: [
        _CartHeader(count: itemsCount, onOpenFavorites: onOpenFavorites),
        const _DeliveryCountryCard(),
        _SelectionBar(
          allSelected: allSelected,
          count: cart.lines.length,
          selectedCount: selectedIds.length,
          onToggleAll: onToggleAll,
          onRemoveSelected: onRemoveSelected,
        ),
        for (final line in cart.lines)
          _CartLineCard(
            line: line,
            selected: selectedIds.contains(line.product.id),
            onToggleSelection: () => onToggleSelection(line.product.id),
          ),
        const _DeliveryInfoCard(),
        _OrderSummary(count: selectedLines.length, total: selectedTotal),
        if (blocked)
          Container(
            margin: const EdgeInsets.fromLTRB(16, 14, 16, 0),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
            decoration: BoxDecoration(color: _cartRedSoft, borderRadius: BorderRadius.circular(10)),
            child: const Row(
              children: [
                Icon(Icons.error_rounded, color: _cartRed, size: 24),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Modifiez les quantités des produits indisponibles avant de continuer.',
                    style: TextStyle(color: _cartRed, fontSize: 13, height: 1.35, fontWeight: FontWeight.w500),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _CartHeader extends StatelessWidget {
  final int count;
  final VoidCallback onOpenFavorites;

  const _CartHeader({required this.count, required this.onOpenFavorites});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 14, 12, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Panier',
                  style: TextStyle(color: _cartNavy, fontSize: 32, height: 1.05, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(
                  '$count article${count > 1 ? 's' : ''}',
                  style: const TextStyle(color: _cartMuted, fontSize: 16, fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
          _FavoritesShortcut(onTap: onOpenFavorites),
        ],
      ),
    );
  }
}

class _FavoritesShortcut extends StatelessWidget {
  final VoidCallback onTap;

  const _FavoritesShortcut({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: const Padding(
        padding: EdgeInsets.symmetric(horizontal: 6, vertical: 4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.favorite_border_rounded, color: _cartNavy, size: 28),
            SizedBox(height: 2),
            Text(
              'Favoris',
              style: TextStyle(color: _cartNavy, fontSize: 12, fontWeight: FontWeight.w500),
            ),
          ],
        ),
      ),
    );
  }
}

class _DeliveryCountryCard extends StatelessWidget {
  const _DeliveryCountryCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(color: _cartSoft, borderRadius: BorderRadius.circular(12)),
      child: const Row(
        children: [
          Icon(Icons.local_shipping_outlined, color: _cartNavy, size: 30),
          SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Livraison en Côte d’Ivoire',
                  style: TextStyle(color: _cartNavy, fontSize: 14.5, fontWeight: FontWeight.w800),
                ),
                SizedBox(height: 3),
                Text('Adresse à renseigner lors de la commande', style: TextStyle(color: _cartMuted, fontSize: 12.5)),
              ],
            ),
          ),
        ],
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
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
      child: Row(
        children: [
          InkWell(
            onTap: onToggleAll,
            borderRadius: BorderRadius.circular(8),
            child: Row(
              children: [
                _SelectionCheckbox(selected: allSelected, onTap: onToggleAll),
                const SizedBox(width: 12),
                Text(
                  'Tout sélectionner ($count)',
                  style: const TextStyle(color: _cartNavy, fontSize: 15, fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
          const Spacer(),
          InkWell(
            onTap: selectedCount == 0 ? null : onRemoveSelected,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Text(
                'Supprimer la sélection',
                style: TextStyle(
                  color: selectedCount == 0 ? _cartRed.withValues(alpha: .4) : _cartRed,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CartLineCard extends StatelessWidget {
  final CartLine line;
  final bool selected;
  final VoidCallback onToggleSelection;

  const _CartLineCard({required this.line, required this.selected, required this.onToggleSelection});

  String _subtitle() {
    final product = line.product;
    if (product.weightKg > 0) return '${ProductModelHelper.compact(product.weightKg)} kg';
    if (product.dimensionsLabel.isNotEmpty) return product.dimensionsLabel;
    if (product.unitLabel.trim().isNotEmpty) return product.unitLabel.trim();
    return '';
  }

  Future<void> _remove(BuildContext context) async {
    await CartStore.instance.removeLineAndSync(line.key);
    if (context.mounted) showCartToast(context, 'Le produit a été retiré de votre panier.');
  }

  @override
  Widget build(BuildContext context) {
    final unavailable = _lineUnavailable(line);
    final unit = shortSaleUnit(line.product.unitLabel);
    final subtitle = _subtitle();

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      padding: const EdgeInsets.fromLTRB(12, 12, 14, 12),
      decoration: BoxDecoration(
        color: unavailable ? const Color(0xFFFFF7F7) : Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: unavailable ? _cartRed : _cartBorder, width: unavailable ? 1.3 : 1),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          _SelectionCheckbox(selected: selected, onTap: onToggleSelection),
          const SizedBox(width: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: SizedBox(width: 84, height: 84, child: _ProductImage(url: line.imageUrl)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            line.product.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: _cartNavy,
                              fontSize: 14.5,
                              height: 1.2,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          if (line.variant != null)
                            Text(
                              line.variant!.label,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: _cartMuted, fontSize: 12.5),
                            )
                          else if (subtitle.isNotEmpty)
                            Text(
                              subtitle,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: _cartMuted, fontSize: 12.5),
                            ),
                          const SizedBox(height: 4),
                          _StockLabel(unavailable: unavailable),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          formatFcfa(line.subtotal),
                          style: const TextStyle(color: _cartNavy, fontSize: 15, fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          '${formatFcfa(line.unitPrice)} / $unit',
                          style: const TextStyle(color: _cartMuted, fontSize: 11.5),
                        ),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    _QuantityControl(line: line, error: unavailable),
                    const Spacer(),
                    InkWell(
                      onTap: () => _remove(context),
                      borderRadius: BorderRadius.circular(8),
                      child: const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 2, vertical: 6),
                        child: Row(
                          children: [
                            Icon(Icons.delete_outline_rounded, color: _cartMuted, size: 20),
                            SizedBox(width: 4),
                            Text('Supprimer', style: TextStyle(color: _cartMuted, fontSize: 13)),
                          ],
                        ),
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
}

class _StockLabel extends StatelessWidget {
  final bool unavailable;

  const _StockLabel({required this.unavailable});

  @override
  Widget build(BuildContext context) {
    if (unavailable) {
      return const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.error_rounded, color: _cartRed, size: 17),
          SizedBox(width: 5),
          Text(
            'Stock insuffisant',
            style: TextStyle(color: _cartRed, fontSize: 13, fontWeight: FontWeight.w500),
          ),
        ],
      );
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 7,
          height: 7,
          decoration: const BoxDecoration(color: _cartGreen, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        const Text(
          'En stock',
          style: TextStyle(color: _cartGreen, fontSize: 13, fontWeight: FontWeight.w500),
        ),
      ],
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
      color: const Color(0xFFF5F6F8),
      padding: const EdgeInsets.all(4),
      child: url.trim().isEmpty
          ? const Icon(Icons.inventory_2_outlined, color: Color(0xFFB9C2D2), size: 36)
          : Image.network(
              url,
              fit: BoxFit.contain,
              cacheWidth: 240,
              errorBuilder: (_, __, ___) => const Icon(Icons.broken_image_outlined, color: Color(0xFFB9C2D2), size: 34),
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
        width: 26,
        height: 26,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? OvanieColors.orange : Colors.white,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: selected ? OvanieColors.orange : const Color(0xFFC9D1DE), width: 1.4),
        ),
        child: selected ? const Icon(Icons.check_rounded, color: Colors.white, size: 19) : null,
      ),
    );
  }
}

class _QuantityControl extends StatelessWidget {
  final CartLine line;
  final bool error;

  const _QuantityControl({required this.line, required this.error});

  @override
  Widget build(BuildContext context) {
    final canIncrement = line.product.canAddToCart && line.quantity < line.availableStock;
    final borderColor = error ? _cartRed : _cartBorder;
    return Container(
      height: 40,
      width: 118,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: borderColor, width: error ? 1.3 : 1),
      ),
      child: Row(
        children: [
          Expanded(
            child: _QtyTap(icon: Icons.remove_rounded, onTap: () => CartStore.instance.decrement(line.key)),
          ),
          Container(width: 1, color: borderColor),
          Expanded(
            child: Center(
              child: Text(
                '${line.quantity}',
                style: const TextStyle(color: _cartNavy, fontSize: 15, fontWeight: FontWeight.w700),
              ),
            ),
          ),
          Container(width: 1, color: borderColor),
          Expanded(
            child: _QtyTap(
              icon: Icons.add_rounded,
              onTap: canIncrement ? () => CartStore.instance.increment(line.key) : null,
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
      child: SizedBox.expand(child: Icon(icon, size: 20, color: onTap == null ? _cartBorder : _cartNavy)),
    );
  }
}

class _DeliveryInfoCard extends StatelessWidget {
  const _DeliveryInfoCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 2, 16, 0),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _cartBorder),
      ),
      child: const Column(
        children: [
          _DeliveryInfoRow(
            icon: Icons.local_shipping_outlined,
            title: 'Livraison estimée',
            lines: ['Délai communiqué après le choix de l’adresse', 'Options adaptées à votre destination'],
          ),
          Divider(height: 1, indent: 16, endIndent: 16, color: _cartBorder),
          _DeliveryInfoRow(
            icon: Icons.inventory_2_outlined,
            title: 'Livraison disponible',
            lines: ['Frais confirmés avant validation'],
          ),
        ],
      ),
    );
  }
}

class _DeliveryInfoRow extends StatelessWidget {
  final IconData icon;
  final String title;
  final List<String> lines;

  const _DeliveryInfoRow({required this.icon, required this.title, required this.lines});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 13, 14, 13),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: const BoxDecoration(color: _cartSoft, shape: BoxShape.circle),
            child: Icon(icon, color: _cartNavy, size: 26),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(color: _cartNavy, fontSize: 14.5, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 2),
                for (final text in lines)
                  Text(text, style: const TextStyle(color: _cartMuted, fontSize: 12.5, height: 1.35)),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: _cartNavy, size: 24),
        ],
      ),
    );
  }
}

class _OrderSummary extends StatelessWidget {
  final int count;
  final double total;

  const _OrderSummary({required this.count, required this.total});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 14, 16, 0),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _cartBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Récapitulatif de la commande',
            style: TextStyle(color: _cartNavy, fontSize: 16.5, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 14),
          _SummaryLine(label: 'Sous-total ($count article${count > 1 ? 's' : ''})', value: formatFcfa(total)),
          const SizedBox(height: 10),
          const _SummaryLine(label: 'Livraison', value: 'À confirmer', muted: true),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 13),
            child: Divider(height: 1, color: _cartBorder),
          ),
          _SummaryLine(label: 'Total à payer', value: formatFcfa(total), emphasis: true),
        ],
      ),
    );
  }
}

class _SummaryLine extends StatelessWidget {
  final String label;
  final String value;
  final bool emphasis;
  final bool muted;

  const _SummaryLine({required this.label, required this.value, this.emphasis = false, this.muted = false});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              color: emphasis ? _cartNavy : _cartMuted,
              fontSize: emphasis ? 17 : 14.5,
              fontWeight: emphasis ? FontWeight.w800 : FontWeight.w400,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Text(
          value,
          style: TextStyle(
            color: muted ? _cartMuted : _cartNavy,
            fontSize: emphasis ? 21 : 14.5,
            fontWeight: emphasis ? FontWeight.w900 : (muted ? FontWeight.w400 : FontWeight.w700),
          ),
        ),
      ],
    );
  }
}

/// Barre fixe du bas : total des produits sélectionnés et passage en caisse.
class _CheckoutBar extends StatelessWidget {
  final double total;
  final bool enabled;
  final bool loading;
  final VoidCallback onCheckout;

  const _CheckoutBar({required this.total, required this.enabled, required this.loading, required this.onCheckout});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 12, 16, 12),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: _cartBorder)),
        boxShadow: [BoxShadow(color: Color(0x0F000000), blurRadius: 12, offset: Offset(0, -3))],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Total à payer', style: TextStyle(color: _cartMuted, fontSize: 13)),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    formatFcfa(total),
                    style: const TextStyle(color: _cartNavy, fontSize: 22, fontWeight: FontWeight.w900),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          SizedBox(
            width: (MediaQuery.sizeOf(context).width * .5).clamp(150.0, 210.0),
            height: 52,
            child: FilledButton(
              onPressed: enabled && !loading ? onCheckout : null,
              style: FilledButton.styleFrom(
                elevation: 0,
                backgroundColor: OvanieColors.orange,
                disabledBackgroundColor: const Color(0xFFB4BCC9),
                foregroundColor: Colors.white,
                disabledForegroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: loading
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        'Passer la commande',
                        maxLines: 1,
                        style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w700),
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyCartView extends StatelessWidget {
  final VoidCallback onStartShopping;
  final VoidCallback onOpenFavorites;

  const _EmptyCartView({required this.onStartShopping, required this.onOpenFavorites});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const BouncingScrollPhysics(),
      slivers: [
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
            child: Row(
              children: [
                const SizedBox(width: 64),
                const Expanded(
                  child: Text(
                    'Panier',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: _cartNavy, fontSize: 20, fontWeight: FontWeight.w800),
                  ),
                ),
                SizedBox(width: 64, child: _FavoritesShortcut(onTap: onOpenFavorites)),
              ],
            ),
          ),
        ),
        SliverFillRemaining(
          hasScrollBody: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const _EmptyCartIllustration(),
                const SizedBox(height: 28),
                const Text(
                  'Votre panier est vide',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: _cartNavy, fontSize: 26, height: 1.12, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 10),
                const Text(
                  'Ajoutez des matériaux, équipements et outils\npour démarrer votre commande.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: _cartMuted, fontSize: 15, height: 1.45),
                ),
                const SizedBox(height: 28),
                SizedBox(
                  width: double.infinity,
                  height: 54,
                  child: FilledButton(
                    onPressed: onStartShopping,
                    style: FilledButton.styleFrom(
                      elevation: 0,
                      backgroundColor: OvanieColors.orange,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text(
                      'Explorer le catalogue',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: OutlinedButton(
                    onPressed: onOpenFavorites,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _cartNavy,
                      side: const BorderSide(color: Color(0xFFCBD3DF)),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Voir mes favoris', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                  ),
                ),
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
    return ClipRRect(
      borderRadius: BorderRadius.circular(24),
      child: SizedBox(
        width: (width * .78).clamp(240.0, 340.0),
        child: AspectRatio(
          aspectRatio: 650 / 607,
          child: Image.asset('assets/images/checkout/cart_empty.png', fit: BoxFit.cover),
        ),
      ),
    );
  }
}

/// Fenêtre « Connectez-vous pour continuer » (maquette du panier).
class _LoginRequiredSheet extends StatelessWidget {
  final VoidCallback onClose;
  final VoidCallback onLogin;
  final VoidCallback onRegister;

  const _LoginRequiredSheet({required this.onClose, required this.onLogin, required this.onRegister});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(22, 10, 22, 22),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 44,
            height: 5,
            decoration: BoxDecoration(color: const Color(0xFFCBD2DC), borderRadius: BorderRadius.circular(3)),
          ),
          Align(
            alignment: Alignment.centerRight,
            child: IconButton(
              onPressed: onClose,
              tooltip: 'Fermer',
              icon: const Icon(Icons.close_rounded, color: _cartMuted, size: 28),
            ),
          ),
          Container(
            width: 80,
            height: 80,
            decoration: const BoxDecoration(color: Color(0xFFEAEEF4), shape: BoxShape.circle),
            child: const Icon(Icons.lock_rounded, color: _cartNavy, size: 38),
          ),
          const SizedBox(height: 18),
          const Text(
            'Connectez-vous pour continuer',
            textAlign: TextAlign.center,
            style: TextStyle(color: _cartNavy, fontSize: 21, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 10),
          const Text(
            'Votre compte permet de sécuriser la commande et de retrouver votre panier sur tous vos appareils.',
            textAlign: TextAlign.center,
            style: TextStyle(color: _cartMuted, fontSize: 14.5, height: 1.45),
          ),
          const SizedBox(height: 22),
          SizedBox(
            width: double.infinity,
            height: 52,
            child: FilledButton(
              onPressed: onLogin,
              style: FilledButton.styleFrom(
                elevation: 0,
                backgroundColor: OvanieColors.orange,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: const Text('Se connecter', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            height: 52,
            child: OutlinedButton(
              onPressed: onRegister,
              style: OutlinedButton.styleFrom(
                foregroundColor: _cartNavy,
                side: const BorderSide(color: _cartNavy, width: 1.4),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: const Text('Créer un compte', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            ),
          ),
        ],
      ),
    );
  }
}
