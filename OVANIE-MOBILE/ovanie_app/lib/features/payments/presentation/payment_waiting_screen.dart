import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/in_app_payment_screen.dart';
import '../../cart/data/cart_api_repository.dart';
import '../../cart/domain/cart_store.dart';
import '../../checkout/presentation/order_confirmation_screen.dart';
import '../../orders/data/orders_repository.dart';
import 'payment_ui.dart';
import 'resume_payment_screen.dart';

/// Vérification du paiement en ligne après le passage par la page sécurisée.
///
/// Le statut vient toujours du serveur : l'écran interroge la commande jusqu'à
/// la confirmation, l'échec ou un délai raisonnable (« Paiement non finalisé »).
class PaymentWaitingScreen extends StatefulWidget {
  final int orderId;
  final String orderNumber;
  final double amount;
  final String methodLabel;
  final String paymentUrl;

  const PaymentWaitingScreen({
    super.key,
    required this.orderId,
    required this.orderNumber,
    required this.amount,
    required this.methodLabel,
    this.paymentUrl = '',
  });

  @override
  State<PaymentWaitingScreen> createState() => _PaymentWaitingScreenState();
}

class _PaymentWaitingScreenState extends State<PaymentWaitingScreen> with WidgetsBindingObserver {
  Timer? _timer;
  bool _checking = false;
  bool _providerReturned = false;
  bool _waiting = true;
  bool _unstable = false;
  bool _resuming = false;
  int _attempts = 0;
  int _attemptsAfterReturn = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _startPolling();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) return;
    _providerReturned = true;
    _attemptsAfterReturn = 0;
    if (mounted) setState(() => _waiting = true);
    _startPolling();
  }

  void _startPolling() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 3), (_) => unawaited(_check()));
    unawaited(_check());
  }

  bool get _attemptsExhausted => (_providerReturned && _attemptsAfterReturn >= 5) || _attempts >= 20;

  Future<void> _check() async {
    if (_checking) return;
    _checking = true;
    try {
      final order = await const OrdersRepository().fetchOrder(widget.orderId);
      if (!mounted) return;
      if (_unstable) setState(() => _unstable = false);

      if (order.isPaymentConfirmed) {
        _timer?.cancel();
        await CartStore.instance.clearLocalMirrorOnly();
        try {
          await const CartApiRepository().refreshLocalCartFromServer();
        } catch (_) {}
        if (!mounted) return;
        await _openResult(order.id, order.orderNumber, order.total, 'completed');
        return;
      }

      if (order.isPaymentFailed || order.isPaymentCancelled) {
        _timer?.cancel();
        await _openResult(order.id, order.orderNumber, order.total, order.isPaymentCancelled ? 'cancelled' : 'failed');
        return;
      }

      _attempts++;
      if (_providerReturned) _attemptsAfterReturn++;
      // Un retour sans paiement ne doit jamais laisser une roue tourner sans fin.
      if (_attemptsExhausted) _stopAsPending();
    } catch (_) {
      _attempts++;
      if (_providerReturned) _attemptsAfterReturn++;
      if (!mounted) return;
      if (_attemptsExhausted) {
        _stopAsPending();
      } else if (_attempts > 2) {
        setState(() => _unstable = true);
      }
    } finally {
      _checking = false;
    }
  }

  void _stopAsPending() {
    _timer?.cancel();
    if (mounted) setState(() => _waiting = false);
  }

  Future<void> _openResult(int id, String number, double amount, String state) {
    return Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => OrderConfirmationScreen(
          orderId: id,
          initialOrderNumber: number,
          initialAmount: amount,
          initialPaymentMethod: widget.methodLabel,
          returnState: state,
        ),
      ),
    );
  }

  Future<void> _checkNow() async {
    _attempts = 0;
    _attemptsAfterReturn = 0;
    setState(() => _waiting = true);
    _startPolling();
  }

  Future<void> _resumePayment() async {
    if (_resuming) return;
    final url = widget.paymentUrl.trim();
    if (url.isNotEmpty) {
      await InAppPaymentScreen.open(context, url);
      if (!mounted) return;
      _providerReturned = false;
      _attempts = 0;
      _attemptsAfterReturn = 0;
      setState(() => _waiting = true);
      _startPolling();
      return;
    }
    // Pas de lien enregistré : on repart de l'écran de paiement Mobile Money.
    setState(() => _resuming = true);
    try {
      final order = await const OrdersRepository().fetchOrder(widget.orderId);
      if (!mounted) return;
      await Navigator.of(
        context,
      ).pushReplacement(MaterialPageRoute<void>(builder: (_) => ResumePaymentScreen(order: order)));
    } catch (_) {
      if (mounted) setState(() => _unstable = true);
    } finally {
      if (mounted) setState(() => _resuming = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final code = operatorCodeOf(widget.methodLabel);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          child: Column(
            children: [
              PaymentFlowHeader(title: 'Vérification du paiement', onBack: () => Navigator.of(context).maybePop()),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 34, 20, 20),
                  children: [
                    Center(
                      child: _waiting
                          ? const SizedBox(
                              width: 64,
                              height: 64,
                              child: CircularProgressIndicator(
                                strokeWidth: 6,
                                color: OvanieColors.orange,
                                backgroundColor: Color(0xFFFFEDE2),
                              ),
                            )
                          : const Icon(Icons.access_time_rounded, color: payAmber, size: 76),
                    ),
                    const SizedBox(height: 26),
                    Text(
                      _waiting ? 'Vérification du paiement' : 'Paiement non finalisé',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: payNavy, fontSize: 26, fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      _waiting
                          ? (_providerReturned
                                ? 'Vérification de la réponse du prestataire…'
                                : 'En attente de la confirmation du prestataire…')
                          : 'Votre paiement n’est pas encore confirmé. Vous pouvez reprendre le paiement ou vérifier à nouveau.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: payMuted, fontSize: 16, height: 1.45),
                    ),
                    if (_unstable) ...[
                      const SizedBox(height: 22),
                      Container(
                        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF8E6),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFF5D27A)),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.warning_amber_rounded, color: Color(0xFFD97706), size: 32),
                            SizedBox(width: 14),
                            Expanded(
                              child: Text(
                                'Connexion instable. Nous continuons de vérifier votre paiement.',
                                style: TextStyle(color: Color(0xFFB45309), fontSize: 15, height: 1.4),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 24),
                    PaymentCard(
                      padding: const EdgeInsets.fromLTRB(18, 6, 18, 6),
                      child: Column(
                        children: [
                          PaymentInfoRow(label: 'Commande', value: widget.orderNumber, verticalPadding: 18),
                          PaymentInfoRow(
                            label: 'Montant',
                            verticalPadding: 16,
                            valueWidget: FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Text(
                                formatFcfa(widget.amount),
                                style: const TextStyle(color: payNavy, fontSize: 23, fontWeight: FontWeight.w900),
                              ),
                            ),
                          ),
                          PaymentInfoRow(
                            label: 'Méthode',
                            last: true,
                            verticalPadding: 14,
                            valueWidget: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                if (code.isNotEmpty) ...[
                                  PaymentOperatorLogo(code: code, size: 44),
                                  const SizedBox(width: 12),
                                ],
                                Text(
                                  code.isEmpty ? widget.methodLabel : operatorLabelOf(code),
                                  style: const TextStyle(color: payNavy, fontSize: 16, fontWeight: FontWeight.w700),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 26),
                    const Text(
                      'Terminez le paiement dans la page sécurisée,\npuis revenez dans OVANIE.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: payMuted, fontSize: 14, height: 1.45),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                child: Column(
                  children: [
                    PaymentButton(
                      label: 'Vérifier le statut maintenant',
                      loading: _checking && !_waiting,
                      onPressed: _checkNow,
                    ),
                    const SizedBox(height: 12),
                    PaymentButton(
                      label: 'Reprendre le paiement',
                      outlined: true,
                      loading: _resuming,
                      onPressed: _resumePayment,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
