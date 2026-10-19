import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../app/theme.dart';
import '../platform/external_url_launcher.dart';

class InAppPaymentScreen extends StatefulWidget {
  final String url;

  const InAppPaymentScreen({super.key, required this.url});

  static Future<void> open(BuildContext context, String url) =>
      Navigator.of(context).push<void>(MaterialPageRoute<void>(builder: (_) => InAppPaymentScreen(url: url)));

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
              setState(() => _error = 'La page de paiement ne répond pas. Vérifiez votre connexion puis réessayez.');
            }
          },
          onNavigationRequest: (request) async {
            final uri = Uri.tryParse(request.url);
            if (uri == null) return NavigationDecision.prevent;
            if (uri.path.contains('/paydunya/mobile/return') || uri.path.contains('/paydunya/mobile/cancel')) {
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

  void _retry() {
    setState(() => _error = null);
    _controller.reload();
  }

  @override
  Widget build(BuildContext context) => AnnotatedRegion<SystemUiOverlayStyle>(
    value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
    child: Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            SizedBox(
              height: 64,
              child: Row(
                children: [
                  IconButton(
                    onPressed: () => Navigator.of(context).maybePop(),
                    tooltip: 'Fermer',
                    icon: const Icon(Icons.close_rounded, color: _navy, size: 30),
                  ),
                  const Expanded(
                    child: Text(
                      'Paiement sécurisé',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: _navy, fontSize: 19, fontWeight: FontWeight.w800),
                    ),
                  ),
                  Container(
                    width: 46,
                    height: 46,
                    margin: const EdgeInsets.only(right: 12),
                    decoration: const BoxDecoration(color: Color(0xFFE6F6EC), shape: BoxShape.circle),
                    child: const Icon(Icons.lock_rounded, color: Color(0xFF15803D), size: 22),
                  ),
                ],
              ),
            ),
            if (_error == null) ...[
              Container(
                width: double.infinity,
                margin: const EdgeInsets.fromLTRB(16, 4, 16, 14),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
                decoration: BoxDecoration(color: const Color(0xFFEAF3FF), borderRadius: BorderRadius.circular(14)),
                child: const Row(
                  children: [
                    Icon(Icons.info_outline_rounded, color: Color(0xFF0B5ED7), size: 26),
                    SizedBox(width: 14),
                    Expanded(
                      child: Text(
                        'Terminez le paiement dans la page sécurisée, puis revenez dans OVANIE.',
                        style: TextStyle(color: Color(0xFF0A3C8C), fontSize: 15, height: 1.4),
                      ),
                    ),
                  ],
                ),
              ),
              if (_progress < 100)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: LinearProgressIndicator(
                    value: _progress / 100,
                    minHeight: 3,
                    color: OvanieColors.orange,
                    backgroundColor: const Color(0xFFFFEDE2),
                  ),
                ),
              Expanded(
                child: Container(
                  margin: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFFDDE3EC)),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: WebViewWidget(controller: _controller),
                ),
              ),
            ] else
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 60),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 108,
                        height: 108,
                        decoration: const BoxDecoration(color: Color(0xFFF1F4F8), shape: BoxShape.circle),
                        child: const Icon(Icons.cloud_off_rounded, color: Color(0xFF4A556E), size: 50),
                      ),
                      const SizedBox(height: 28),
                      Text(
                        _error!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: _navy, fontSize: 17, height: 1.45),
                      ),
                      const SizedBox(height: 28),
                      SizedBox(
                        width: double.infinity,
                        height: 54,
                        child: FilledButton(
                          onPressed: _retry,
                          style: FilledButton.styleFrom(
                            elevation: 0,
                            backgroundColor: OvanieColors.orange,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          child: const Text('Réessayer', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                        ),
                      ),
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

const Color _navy = Color(0xFF0A1B4F);
