import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../app/theme.dart';
import '../platform/external_url_launcher.dart';

class InAppPaymentScreen extends StatefulWidget {
  final String url;

  const InAppPaymentScreen({super.key, required this.url});

  static Future<void> open(BuildContext context, String url) =>
      Navigator.of(context).push<void>(
        MaterialPageRoute<void>(builder: (_) => InAppPaymentScreen(url: url)),
      );

  @override
  State<InAppPaymentScreen> createState() => _InAppPaymentScreenState();
}

class _InAppPaymentScreenState extends State<InAppPaymentScreen> {
  late final WebViewController _controller;
  int _progress = 0;
  String? _error;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (value) {
            if (mounted) setState(() => _progress = value);
          },
          onPageStarted: (_) {
            if (mounted) setState(() => _error = null);
          },
          onPageFinished: (_) async {
            // Les pages Mobile Money ouvrent parfois l'application de paiement
            // avec target=_blank/window.open. Android WebView n'affiche pas ces
            // nouvelles fenetres : on les redirige donc dans la vue courante,
            // afin que NavigationDelegate puisse ensuite lancer Wave/Orange/etc.
            await _controller.runJavaScript(r'''
              (function () {
                document.querySelectorAll('a[target="_blank"]').forEach(function (link) {
                  link.setAttribute('target', '_self');
                });
                window.open = function (url) {
                  if (url) window.location.href = url;
                  return window;
                };
              })();
            ''');
          },
          onWebResourceError: (error) {
            if (error.isForMainFrame == true && mounted) {
              setState(
                () => _error =
                    'La page de paiement ne répond pas. Vérifiez votre connexion puis réessayez.',
              );
            }
          },
          onNavigationRequest: (request) async {
            final uri = Uri.tryParse(request.url);
            if (uri == null) return NavigationDecision.prevent;
            if (uri.path.contains('/paydunya/mobile/return') ||
                uri.path.contains('/paydunya/mobile/cancel')) {
              if (mounted) Navigator.of(context).pop();
              return NavigationDecision.prevent;
            }
            if (uri.scheme != 'http' && uri.scheme != 'https') {
              final opened = await ExternalUrlLauncher.open(request.url);
              if (!opened && mounted) {
                setState(
                  () => _error =
                      'Impossible d’ouvrir l’application de paiement. Vérifiez qu’elle est installée puis réessayez.',
                );
              }
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.url));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Paiement sécurisé'),
      backgroundColor: Colors.white,
      foregroundColor: OvanieColors.navy,
    ),
    body: Column(
      children: [
        if (_progress < 100)
          LinearProgressIndicator(
            value: _progress / 100,
            color: OvanieColors.orange,
          ),
        Expanded(
          child: _error == null
              ? WebViewWidget(controller: _controller)
              : Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.wifi_off_rounded, size: 44),
                        const SizedBox(height: 12),
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        FilledButton.icon(
                          onPressed: () {
                            setState(() => _error = null);
                            _controller.reload();
                          },
                          icon: const Icon(Icons.refresh_rounded),
                          label: const Text('Réessayer'),
                        ),
                      ],
                    ),
                  ),
                ),
        ),
      ],
    ),
  );
}
