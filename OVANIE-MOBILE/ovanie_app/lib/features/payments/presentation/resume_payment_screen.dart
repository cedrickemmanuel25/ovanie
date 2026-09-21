import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/reference_data/reference_data_store.dart';
import '../../../core/widgets/in_app_payment_screen.dart';
import '../../auth/domain/session_store.dart';
import '../../checkout/data/checkout_repository.dart';
import '../../checkout/presentation/order_confirmation_screen.dart';
import '../../orders/data/orders_repository.dart';
import '../../orders/domain/order_model.dart';

class ResumePaymentScreen extends StatefulWidget {
  final MobileOrder order;
  const ResumePaymentScreen({super.key, required this.order});

  @override
  State<ResumePaymentScreen> createState() => _ResumePaymentScreenState();
}

class _ResumePaymentScreenState extends State<ResumePaymentScreen> {
  final _checkout = const CheckoutRepository();
  final _orders = const OrdersRepository();
  final _phone = TextEditingController();
  String _operator = 'wave';
  bool _busy = false;
  String? _message;
  Timer? _poller;

  List<(String, String)> get _operators {
    final remote =
        OvanieReferenceDataStore.instance.options('checkout_operators');
    if (remote.isNotEmpty) {
      return remote
          .map((item) => (item.code, item.label))
          .toList(growable: false);
    }
    return const <(String, String)>[];
  }

  @override
  void initState() {
    super.initState();
    _phone.text = SessionStore.instance.phone ?? '';
  }

  @override
  void dispose() {
    _poller?.cancel();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _start() async {
    if (_busy || !widget.order.actions.canResumePayment) return;
    if (_operators.isEmpty) {
      setState(() {
        _message =
            'Les moyens de paiement OVANIE sont indisponibles. Actualisez puis réessayez.';
      });
      return;
    }
    if (_operator != 'card' && _phone.text.trim().isEmpty) {
      setState(() => _message = 'Renseignez le numéro Mobile Money à débiter.');
      return;
    }

    setState(() {
      _busy = true;
      _message = null;
    });
    try {
      final result = await _checkout.startOnlinePayment(
        orderId: widget.order.id,
        operator: _operator,
        phone: _phone.text,
      );
      if (!mounted) return;
      if (result.paymentUrl.trim().isNotEmpty) {
        await InAppPaymentScreen.open(context, result.paymentUrl);
        if (!mounted) return;
      }
      setState(() {
        _message =
            'Paiement lancé. OVANIE vérifie maintenant sa validation auprès du serveur.';
        _busy = false;
      });
      _startPolling();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _message = ApiClient.friendlyError(error);
      });
    }
  }

  void _startPolling() {
    _poller?.cancel();
    var attempts = 0;
    _poller = Timer.periodic(const Duration(seconds: 4), (timer) async {
      attempts++;
      if (attempts > 45) {
        timer.cancel();
        return;
      }
      try {
        final order = await _orders.fetchOrder(widget.order.id);
        if (!mounted) return;
        if (order.isPaymentConfirmed ||
            order.isPaymentFailed ||
            order.isPaymentCancelled ||
            !order.actions.canResumePayment) {
          timer.cancel();
          Navigator.of(context).pushReplacement(
            MaterialPageRoute<void>(
              builder: (_) => OrderConfirmationScreen(
                orderId: order.id,
                initialOrderNumber: order.orderNumber,
                initialAmount: order.total,
                initialPaymentMethod: order.paymentMethodLabel,
              ),
            ),
          );
        }
      } catch (_) {
        // L'écran réseau global prend le relais si le réseau mobile est coupé.
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Reprendre le paiement')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: OvanieColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '#${widget.order.orderNumber}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 17,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Statut actuel : ${widget.order.paymentAttemptStatusLabel.isNotEmpty ? widget.order.paymentAttemptStatusLabel : widget.order.paymentStatusLabel}',
                  style: const TextStyle(color: OvanieColors.muted),
                ),
                if (widget.order.outstandingAmount > 0) ...[
                  const SizedBox(height: 6),
                  Text(
                    'Reste à payer : ${widget.order.outstandingAmount.round()} FCFA',
                    style: const TextStyle(
                      color: OvanieColors.orange,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 20),
          const Text(
            'Choisir le moyen de paiement',
            style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _operators
                .map(
                  (entry) => ChoiceChip(
                    selected: _operator == entry.$1,
                    label: Text(entry.$2),
                    onSelected: _busy
                        ? null
                        : (_) => setState(() => _operator = entry.$1),
                  ),
                )
                .toList(growable: false),
          ),
          if (_operator != 'card') ...[
            const SizedBox(height: 16),
            TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Numéro Mobile Money',
                prefixIcon: Icon(Icons.phone_android_rounded),
              ),
            ),
          ],
          const SizedBox(height: 12),
          const Text(
            'OVANIE ne confirme jamais un paiement localement : le statut final vient du même backend Laravel et du même prestataire que le Web.',
            style: TextStyle(
              color: OvanieColors.muted,
              fontSize: 11.5,
              height: 1.4,
            ),
          ),
          if (_message != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: OvanieColors.blue.withValues(alpha: .06),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                _message!,
                style: const TextStyle(fontSize: 11.5, height: 1.4),
              ),
            ),
          ],
          const SizedBox(height: 18),
          FilledButton.icon(
            onPressed: _busy || _operators.isEmpty ? null : _start,
            style: FilledButton.styleFrom(
              backgroundColor: OvanieColors.orange,
              minimumSize: const Size.fromHeight(50),
            ),
            icon: _busy
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.lock_outline_rounded),
            label: Text(
              _busy ? 'Initialisation…' : 'Continuer vers le paiement sécurisé',
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: _busy
                ? null
                : () async {
                    final order = await _orders.fetchOrder(widget.order.id);
                    if (!context.mounted) return;
                    Navigator.of(context).pushReplacement(
                      MaterialPageRoute<void>(
                        builder: (_) => OrderConfirmationScreen(
                          orderId: order.id,
                          initialOrderNumber: order.orderNumber,
                        ),
                      ),
                    );
                  },
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Vérifier le statut maintenant'),
          ),
        ],
      ),
    );
  }
}
