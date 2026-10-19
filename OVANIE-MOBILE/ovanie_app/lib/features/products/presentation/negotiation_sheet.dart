import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/network/api_client.dart';
import '../../../core/storage/device_storage.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../auth/presentation/login_screen.dart';
import '../../auth/presentation/register_screen.dart';
import '../../cart/domain/cart_store.dart';
import '../../categories/presentation/catalog_widgets.dart';
import '../data/negotiation_repository.dart';
import '../domain/product_model.dart';

String _negotiateExpiredKey(int productId) => 'ov_negotiate_expired_$productId';
String _negotiateFinalStartedKey(int productId) => 'ov_negotiate_final_started_$productId';

/// Bouton « Négocier » de la fiche produit. Ouvre la négociation guidée, ou
/// la demande de connexion pour un visiteur. Une fois la dernière offre
/// expirée, il reste désactivé pour ce produit.
class NegotiateButton extends StatefulWidget {
  const NegotiateButton({super.key, required this.product, required this.quantity});

  final ProductModel product;
  final int quantity;

  @override
  State<NegotiateButton> createState() => _NegotiateButtonState();
}

class _NegotiateButtonState extends State<NegotiateButton> {
  bool _checkingExpiry = true;
  bool _expired = false;

  @override
  void initState() {
    super.initState();
    _loadExpiryState();
  }

  Future<void> _loadExpiryState() async {
    final raw = await DeviceStorage.instance.readString(_negotiateExpiredKey(widget.product.id));
    if (!mounted) return;
    setState(() {
      _expired = raw == '1';
      _checkingExpiry = false;
    });
  }

  Future<void> _openNegotiation() async {
    if (_expired) return;

    if (!SessionStore.instance.isAuthenticated) {
      final action = await _showNegotiationAuthRequired(context);
      if (!mounted || action == null) return;

      final connected = await Navigator.of(context).push<bool>(
        MaterialPageRoute<bool>(
          builder: (_) => action == _NegotiateAuthAction.login ? const LoginScreen() : const RegisterScreen(),
        ),
      );

      if (!mounted) return;
      if (connected != true && !SessionStore.instance.isAuthenticated) return;
    }

    if (!mounted) return;
    await _showPlainSheet<void>(context, (_) => _NegotiationSheet(product: widget.product, quantity: widget.quantity));
    // La fenêtre peut avoir été fermée après l'expiration de la dernière offre.
    await _loadExpiryState();
  }

  @override
  Widget build(BuildContext context) {
    if (_checkingExpiry) return const SizedBox.shrink();

    if (_expired) {
      return Container(
        constraints: const BoxConstraints(maxWidth: 150),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(7),
          border: Border.all(color: const Color(0xFFC9CFDA)),
        ),
        child: const Text(
          'Négociation expirée pour ce produit',
          textAlign: TextAlign.center,
          style: TextStyle(color: Color(0xFF8A93A8), fontSize: 10.5, height: 1.25, fontWeight: FontWeight.w600),
        ),
      );
    }

    return OutlinedButton.icon(
      onPressed: _openNegotiation,
      icon: const Icon(Icons.handshake_outlined, size: 18),
      label: const Text(
        'Négocier',
        style: TextStyle(fontFamily: authFont, fontSize: 12.5, fontWeight: FontWeight.w700),
      ),
      style: OutlinedButton.styleFrom(
        foregroundColor: const Color(0xFF0A2A6B),
        backgroundColor: Colors.white,
        minimumSize: const Size(0, 34),
        padding: const EdgeInsets.symmetric(horizontal: 12),
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        side: const BorderSide(color: Color(0xFF0A2A6B), width: 1.2),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
      ),
    );
  }
}

/// Fenêtre du bas sans titre : poignée puis contenu centré.
Future<T?> _showPlainSheet<T>(BuildContext context, WidgetBuilder builder) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    barrierColor: const Color(0x99000000),
    constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .86),
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (context) => OvanieAuthTheme(
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const CatalogSheetHandle(),
              Flexible(child: SingleChildScrollView(child: builder(context))),
            ],
          ),
        ),
      ),
    ),
  );
}

/// Pastille ronde colorée portant le pictogramme de la fenêtre.
class _SheetBadge extends StatelessWidget {
  const _SheetBadge({required this.icon, required this.color, required this.background, this.filled = false});

  final IconData icon;
  final Color color;
  final Color background;

  /// Disque plein avec pictogramme blanc (confirmation).
  final bool filled;

  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      width: filled ? 78 : 58,
      height: filled ? 78 : 58,
      decoration: BoxDecoration(color: filled ? color : background, shape: BoxShape.circle),
      child: Icon(icon, color: filled ? Colors.white : color, size: filled ? 46 : 30),
    ),
  );
}

const TextStyle _sheetTitle = TextStyle(color: authNavy, fontSize: 19, height: 1.25, fontWeight: FontWeight.w800);

OutlinedButton _outlineAction(String label, VoidCallback? onPressed, {Color color = authOrange}) => OutlinedButton(
  onPressed: onPressed,
  style: OutlinedButton.styleFrom(
    foregroundColor: color,
    minimumSize: const Size.fromHeight(44),
    side: BorderSide(color: color, width: 1.3),
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
  ),
  child: Text(
    label,
    style: const TextStyle(fontFamily: authFont, fontSize: 14, fontWeight: FontWeight.w700),
  ),
);

enum _NegotiateAuthAction { login, register }

Future<_NegotiateAuthAction?> _showNegotiationAuthRequired(BuildContext context) {
  return _showPlainSheet<_NegotiateAuthAction>(
    context,
    (sheetContext) => Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 6),
        const _SheetBadge(icon: Icons.lock_rounded, color: authNavy, background: Color(0xFFE6EEFB)),
        const SizedBox(height: 16),
        const Text('Connectez-vous pour négocier', textAlign: TextAlign.center, style: _sheetTitle),
        const SizedBox(height: 8),
        const Text(
          'La négociation d’un prix est réservée aux clients connectés OVANIE.',
          textAlign: TextAlign.center,
          style: TextStyle(color: catalogMuted, fontSize: 13, height: 1.45),
        ),
        const SizedBox(height: 18),
        OvaniePrimaryButton(
          label: 'Se connecter',
          height: 46,
          onPressed: () => Navigator.of(sheetContext).pop(_NegotiateAuthAction.login),
        ),
        const SizedBox(height: 10),
        _outlineAction('Créer un compte', () => Navigator.of(sheetContext).pop(_NegotiateAuthAction.register)),
      ],
    ),
  );
}

/// Négociation guidée : offres réelles une par une (price_p1/p2/p3 côté
/// serveur), « Ajouter au panier à ce prix » ou « Voir un meilleur prix »,
/// et un compte à rebours sur la dernière offre.
class _NegotiationSheet extends StatefulWidget {
  const _NegotiationSheet({required this.product, required this.quantity});

  final ProductModel product;
  final int quantity;

  @override
  State<_NegotiationSheet> createState() => _NegotiationSheetState();
}

class _NegotiationSheetState extends State<_NegotiationSheet> {
  static const NegotiationRepository _repository = NegotiationRepository();

  bool _loading = true;
  Object? _loadError;
  List<int> _offers = const [];
  int _finalOfferTtlSeconds = 120;
  int _stepIndex = 0;
  int? _remainingSeconds;
  Timer? _timer;
  bool _submitting = false;
  bool _accepted = false;

  /// La dernière offre a expiré : la négociation est close pour ce produit.
  bool _expired = false;

  /// Le serveur a refusé l'offre affichée : on peut recharger les offres.
  bool _unavailable = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadOffers();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  bool get _isLastStep => _offers.isNotEmpty && _stepIndex >= _offers.length - 1;

  int get _effectiveQuantity {
    final minimum = widget.product.minOrderQuantity <= 0 ? 1 : widget.product.minOrderQuantity;
    return widget.quantity < minimum ? minimum : widget.quantity;
  }

  Future<void> _loadOffers() async {
    setState(() {
      _loading = true;
      _loadError = null;
      _unavailable = false;
      _errorMessage = null;
    });

    try {
      final offers = await _repository.getOffers(widget.product.slug);
      if (!mounted) return;
      setState(() {
        _offers = offers.amounts;
        _finalOfferTtlSeconds = offers.finalOfferTtlSeconds;
        _stepIndex = 0;
        _loading = false;
      });
      unawaited(_maybeStartTimer());
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loadError = error;
        _loading = false;
      });
    }
  }

  Future<void> _maybeStartTimer() async {
    _timer?.cancel();
    if (!_isLastStep) {
      if (mounted) setState(() => _remainingSeconds = null);
      return;
    }

    final key = _negotiateFinalStartedKey(widget.product.id);
    final raw = await DeviceStorage.instance.readString(key);
    var startedAtMs = int.tryParse(raw ?? '');
    if (startedAtMs == null) {
      startedAtMs = DateTime.now().millisecondsSinceEpoch;
      await DeviceStorage.instance.writeString(key, '$startedAtMs');
    }
    final effectiveStart = startedAtMs;

    void tick() {
      final elapsedMs = DateTime.now().millisecondsSinceEpoch - effectiveStart;
      final remainingMs = (_finalOfferTtlSeconds * 1000) - elapsedMs;
      if (remainingMs <= 0) {
        _timer?.cancel();
        unawaited(_expireNegotiation());
        return;
      }
      if (!mounted) return;
      setState(() => _remainingSeconds = (remainingMs / 1000).ceil());
    }

    tick();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => tick());
  }

  Future<void> _expireNegotiation() async {
    await DeviceStorage.instance.writeString(_negotiateExpiredKey(widget.product.id), '1');
    await DeviceStorage.instance.remove(_negotiateFinalStartedKey(widget.product.id));
    if (!mounted) return;
    setState(() => _expired = true);
  }

  void _showNext() {
    if (_isLastStep) return;
    _timer?.cancel();
    setState(() {
      _stepIndex += 1;
      _remainingSeconds = null;
      _errorMessage = null;
    });
    unawaited(_maybeStartTimer());
  }

  Future<void> _acceptCurrentOffer() async {
    if (_offers.isEmpty || _submitting) return;
    final proposedPrice = _offers[_stepIndex];

    setState(() {
      _submitting = true;
      _errorMessage = null;
    });

    try {
      final result = await _repository.acceptOffer(widget.product.slug, proposedPrice);
      if (!result.accepted || result.negotiationId == null) {
        if (!mounted) return;
        setState(() {
          _submitting = false;
          _unavailable = true;
          _errorMessage = result.message.isNotEmpty ? result.message : null;
        });
        return;
      }

      await _repository.addNegotiatedToCart(
        productId: widget.product.id,
        negotiatedPrice: proposedPrice,
        negotiationId: result.negotiationId!,
        quantity: _effectiveQuantity,
      );

      CartStore.instance.applyNegotiatedLine(widget.product, _effectiveQuantity, proposedPrice.toDouble());

      _timer?.cancel();
      await DeviceStorage.instance.remove(_negotiateFinalStartedKey(widget.product.id));

      if (!mounted) return;
      setState(() {
        _submitting = false;
        _accepted = true;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _errorMessage = ApiClient.friendlyError(error);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 60),
        child: Center(child: CircularProgressIndicator(color: authOrange)),
      );
    }

    if (_accepted) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(height: 60),
          const _SheetBadge(
            icon: Icons.check_rounded,
            color: Color(0xFF12A84B),
            background: Colors.white,
            filled: true,
          ),
          const SizedBox(height: 26),
          const Text('Produit ajouté au panier à ce prix !', textAlign: TextAlign.center, style: _sheetTitle),
          const SizedBox(height: 70),
          _outlineAction('Fermer', () => Navigator.of(context).pop(), color: authNavy),
        ],
      );
    }

    if (_expired || _unavailable || _loadError != null || _offers.isEmpty) {
      final message = _loadError != null
          ? ApiClient.friendlyError(_loadError!)
          : (_errorMessage ?? 'Cette offre n’est plus disponible.');
      return Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(height: 10),
          const _SheetBadge(icon: Icons.schedule_rounded, color: Color(0xFF4A5368), background: Color(0xFFEFF0F3)),
          const SizedBox(height: 14),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: authNavy, fontSize: 14.5, height: 1.35, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 18),
          // Une négociation expirée ne se relance pas : on ne propose que de fermer.
          _expired
              ? OvaniePrimaryButton(label: 'Fermer', height: 46, onPressed: () => Navigator.of(context).pop())
              : OvaniePrimaryButton(label: 'Réessayer', height: 46, onPressed: _loadOffers),
        ],
      );
    }

    final product = widget.product;
    final seconds = _remainingSeconds;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 6),
        const _SheetBadge(icon: Icons.handshake_outlined, color: authOrange, background: Color(0xFFFFE9DB)),
        const SizedBox(height: 10),
        const Text('Proposition OVANIE', textAlign: TextAlign.center, style: _sheetTitle),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(7),
          decoration: BoxDecoration(color: const Color(0xFFF4F6F9), borderRadius: BorderRadius.circular(8)),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: SizedBox(
                  width: 56,
                  height: 38,
                  child: CatalogImage(
                    urls: product.imageUrl.trim().isEmpty ? const <String>[] : <String>[product.imageUrl],
                    fit: BoxFit.contain,
                    placeholder: const ColoredBox(color: Color(0xFFE9ECF1)),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  product.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: authNavy, fontSize: 13.5, height: 1.25, fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(color: const Color(0xFFF4F6F9), borderRadius: BorderRadius.circular(8)),
          child: IntrinsicHeight(
            child: Row(
              children: [
                Expanded(
                  child: _PriceColumn(label: 'Prix affiché', value: formatFcfa(product.homeDisplayPrice), struck: true),
                ),
                const VerticalDivider(width: 1, thickness: 1, color: Color(0xFFD9DEE8)),
                Expanded(
                  child: _PriceColumn(label: 'Prix proposé', value: formatFcfa(_offers[_stepIndex])),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        if (_isLastStep)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: const Color(0xFFFFF6E5),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFF6D9A0)),
            ),
            child: Row(
              children: [
                const Icon(Icons.schedule_rounded, color: Color(0xFFB45309), size: 26),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Dernière offre possible sur ce produit.',
                        style: TextStyle(color: Color(0xFFB45309), fontSize: 12.5, fontWeight: FontWeight.w700),
                      ),
                      if (seconds != null) ...[
                        const SizedBox(height: 2),
                        Text.rich(
                          TextSpan(
                            text: 'Dernière offre : ',
                            children: [
                              TextSpan(
                                text: _formatDuration(seconds),
                                style: const TextStyle(color: authOrange, fontWeight: FontWeight.w800),
                              ),
                              const TextSpan(text: ' restantes'),
                            ],
                          ),
                          style: const TextStyle(color: Color(0xFFB45309), fontSize: 12),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          )
        else
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 2),
            child: Text(
              'OVANIE vous propose ce prix. Vous pouvez demander mieux.',
              textAlign: TextAlign.center,
              style: TextStyle(color: catalogMuted, fontSize: 12, height: 1.35),
            ),
          ),
        if (_errorMessage != null) ...[
          const SizedBox(height: 8),
          Text(
            _errorMessage!,
            textAlign: TextAlign.center,
            style: const TextStyle(color: authDanger, fontSize: 12, fontWeight: FontWeight.w600),
          ),
        ],
        const SizedBox(height: 12),
        OvaniePrimaryButton(
          label: 'Ajouter au panier à ce prix',
          height: 46,
          loading: _submitting,
          onPressed: _acceptCurrentOffer,
        ),
        if (!_isLastStep) ...[
          const SizedBox(height: 9),
          _outlineAction('Voir un meilleur prix', _submitting ? null : _showNext),
        ],
      ],
    );
  }
}

class _PriceColumn extends StatelessWidget {
  const _PriceColumn({required this.label, required this.value, this.struck = false});

  final String label;
  final String value;
  final bool struck;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(label, style: const TextStyle(color: catalogMuted, fontSize: 12)),
          const SizedBox(height: 4),
          SizedBox(
            height: 32,
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                value,
                maxLines: 1,
                style: struck
                    ? const TextStyle(
                        color: Color(0xFF6B7591),
                        fontSize: 17,
                        fontWeight: FontWeight.w600,
                        decoration: TextDecoration.lineThrough,
                        decorationColor: Color(0xFF6B7591),
                      )
                    : const TextStyle(color: Color(0xFF14843A), fontSize: 23, fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

String _formatDuration(int totalSeconds) {
  final minutes = totalSeconds ~/ 60;
  final seconds = totalSeconds % 60;
  return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
}
