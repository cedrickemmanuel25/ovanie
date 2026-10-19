import 'package:flutter/material.dart';

import '../../../core/platform/external_url_launcher.dart';
import '../../payments/presentation/payment_ui.dart';

const _navy = Color(0xFF0A1B3D);
const _whatsappGreen = Color(0xFF25C35E);

/// Passerelle vers la conversation WhatsApp du support OVANIE.
class SupportWhatsappScreen extends StatelessWidget {
  final String number;
  final String url;

  const SupportWhatsappScreen({super.key, required this.number, required this.url});

  Future<void> _open(BuildContext context) async {
    final opened = url.trim().isNotEmpty && await ExternalUrlLauncher.open(url);
    if (!opened && context.mounted) {
      showOvanieToast(context, 'WhatsApp n’a pas pu être ouvert sur cet appareil.', error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          onPressed: () => Navigator.maybePop(context),
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
        ),
        title: const Text(
          'WhatsApp Support OVANIE',
          style: TextStyle(color: _navy, fontSize: 18, fontWeight: FontWeight.w800),
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 16),
          child: Column(
            children: [
              const Spacer(flex: 3),
              Image.asset('assets/images/checkout/whatsapp_hero.png', width: 150),
              const SizedBox(height: 26),
              const Text(
                'Support WhatsApp OVANIE',
                textAlign: TextAlign.center,
                style: TextStyle(color: _navy, fontSize: 23, fontWeight: FontWeight.w800, letterSpacing: -0.3),
              ),
              const SizedBox(height: 8),
              const Text(
                'Échangez directement avec notre équipe sur WhatsApp.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Color(0xFF6B7890), fontSize: 15, height: 1.45),
              ),
              if (number.trim().isNotEmpty) ...[
                const SizedBox(height: 10),
                Text(
                  number.trim(),
                  style: const TextStyle(color: _navy, fontSize: 15.5, fontWeight: FontWeight.w700),
                ),
              ],
              const Spacer(flex: 4),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: FilledButton(
                  onPressed: () => _open(context),
                  style: FilledButton.styleFrom(
                    backgroundColor: _whatsappGreen,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Image.asset('assets/images/checkout/whatsapp_glyph.png', width: 28, height: 28),
                      const SizedBox(width: 12),
                      const Text('Ouvrir WhatsApp', style: TextStyle(fontSize: 16.5, fontWeight: FontWeight.w800)),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
