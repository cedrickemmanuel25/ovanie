import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/platform/external_url_launcher.dart';

class SupportWhatsappScreen extends StatelessWidget {
  final String number;
  final String url;
  const SupportWhatsappScreen({super.key, required this.number, required this.url});

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('WhatsApp Support OVANIE')),
        body: ListView(
          padding: const EdgeInsets.all(18),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18), border: Border.all(color: OvanieColors.border)),
              child: Column(children: [
                const CircleAvatar(radius: 34, backgroundColor: Color(0xFFE9F8EF), child: Icon(Icons.chat_rounded, size: 34, color: OvanieColors.success)),
                const SizedBox(height: 14),
                const Text('Support WhatsApp OVANIE', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900)),
                const SizedBox(height: 7),
                Text(number, style: const TextStyle(color: OvanieColors.text, fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                const Text('Ce bouton n’apparaît que lorsque le backend Support Center déclare WhatsApp opérationnel et fournit un numéro public réel.', textAlign: TextAlign.center, style: TextStyle(color: OvanieColors.muted, height: 1.4)),
                const SizedBox(height: 20),
                FilledButton.icon(
                  onPressed: url.trim().isEmpty ? null : () async {
                    final opened = await ExternalUrlLauncher.open(url);
                    if (!opened && context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('WhatsApp n’a pas pu être ouvert sur cet appareil.')));
                  },
                  style: FilledButton.styleFrom(backgroundColor: OvanieColors.success, minimumSize: const Size.fromHeight(50)),
                  icon: const Icon(Icons.open_in_new_rounded),
                  label: const Text('Ouvrir WhatsApp', style: TextStyle(fontWeight: FontWeight.w900)),
                ),
              ]),
            ),
          ],
        ),
      );
}
