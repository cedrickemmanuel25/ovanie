import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';

class ForgotPasswordScreen extends StatefulWidget {
  final String? initialIdentifier;

  /// Aperçu de design uniquement : affiche directement l'état « lien envoyé ».
  @visibleForTesting
  final String? previewSentMessage;

  const ForgotPasswordScreen({super.key, this.initialIdentifier, this.previewSentMessage});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _identifier;
  bool _sending = false;
  String? _error;
  String? _success;

  @override
  void initState() {
    super.initState();
    _identifier = TextEditingController(text: widget.initialIdentifier ?? '');
    _success = widget.previewSentMessage;
  }

  @override
  void dispose() {
    _identifier.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    FocusScope.of(context).unfocus();
    if (!(_form.currentState?.validate() ?? true)) return;
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final message = await VendorRepository.instance.forgotPassword(_identifier.text);
      if (!mounted) return;
      setState(() => _success = message);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final sent = _success != null;

    return _ForgotScaffold(
      sent: sent,
      title: sent ? 'Vérifiez votre boîte e-mail' : 'Mot de passe oublié ?',
      subtitle: sent
          ? 'Les instructions de réinitialisation viennent d’être envoyées.'
          : 'Indiquez l’adresse e-mail de votre compte vendeur : nous vous envoyons un lien sécurisé.',
      children: sent ? _sentView() : _formView(),
    );
  }

  List<Widget> _formView() {
    return <Widget>[
      Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (_error != null) ...<Widget>[
              MkNotice(_error!),
              const SizedBox(height: 16),
            ],
            const MkLabel('Adresse e-mail'),
            TextFormField(
              controller: _identifier,
              enabled: !_sending,
              style: mkFieldText,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.done,
              autovalidateMode: AutovalidateMode.onUserInteraction,
              autofillHints: const <String>[AutofillHints.email],
              onFieldSubmitted: (_) => _send(),
              decoration: mkInput(hint: 'exemple@domaine.com', icon: Icons.mail_outline_rounded).copyWith(
                fillColor: const Color(0xFFEEF1F7),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: Color(0xFFEEF1F7)),
                ),
              ),
              validator: (value) {
                final email = (value ?? '').trim();
                if (email.isEmpty) return 'Renseignez votre adresse e-mail.';
                if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
                  return 'Cette adresse e-mail n’est pas valide.';
                }
                return null;
              },
            ),
            const SizedBox(height: 8),
            const Text(
              'Le lien est envoyé à l’adresse associée à votre compte.',
              style: TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.35),
            ),
            const SizedBox(height: 22),
            MkButton(
              label: _sending ? 'Envoi en cours…' : 'Envoyer le lien',
              loading: _sending,
              onPressed: _send,
            ),
          ],
        ),
      ),
      const SizedBox(height: 12),
      MkOutlineButton(
        label: 'Retour à la connexion',
        onPressed: _sending ? null : () => Navigator.of(context).maybePop(),
      ),
    ];
  }

  List<Widget> _sentView() {
    return <Widget>[
      Container(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        decoration: BoxDecoration(color: const Color(0xFFEEF1F7), borderRadius: BorderRadius.circular(12)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Text(
              'Envoyé à',
              style: TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 2),
            Text(
              _identifier.text.trim().toLowerCase(),
              style: const TextStyle(color: Mk.navy, fontSize: 15.5, fontWeight: FontWeight.w800),
            ),
          ],
        ),
      ),
      const SizedBox(height: 16),
      const Text(
        'Et maintenant ?',
        style: TextStyle(color: Mk.navy, fontSize: 15.5, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 12),
      const _Step(number: '1', label: 'Ouvrez l’e-mail reçu d’OVANIE (pensez aux courriers indésirables).'),
      const SizedBox(height: 12),
      const _Step(number: '2', label: 'Touchez le lien : il ouvre directement cette application.'),
      const SizedBox(height: 12),
      const _Step(number: '3', label: 'Choisissez votre nouveau mot de passe.'),
      const SizedBox(height: 20),
      MkButton(
        label: 'Retour à la connexion',
        arrow: false,
        onPressed: () => Navigator.of(context).maybePop(),
      ),
      const SizedBox(height: 10),
      MkOutlineButton(
        label: 'Utiliser une autre adresse',
        onPressed: _sending ? null : () => setState(() => _success = null),
      ),
    ];
  }
}

/// Photo du rayon serrurerie en haut, carte blanche qui la chevauche, pastille
/// ronde à cheval sur le bord de la carte.
class _ForgotScaffold extends StatelessWidget {
  const _ForgotScaffold({required this.sent, required this.title, required this.subtitle, required this.children});

  final bool sent;
  final String title, subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final padding = MediaQuery.paddingOf(context);
    return MkTheme(
      lightStatusBar: true,
      child: Scaffold(
        backgroundColor: Mk.page,
        body: LayoutBuilder(
          builder: (context, viewport) {
            final width = viewport.maxWidth;
            final photoHeight = (width * 680 / 941).clamp(0.0, viewport.maxHeight * .46);
            const badge = 68.0;
            final cardTop = photoHeight - 34;
            return SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: viewport.maxHeight),
                child: Stack(
                  children: <Widget>[
                    Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      height: photoHeight,
                      child: Image.asset(
                        'assets/images/mockups/forgot.jpg',
                        fit: BoxFit.cover,
                        alignment: Alignment.topCenter,
                        semanticLabel: 'Rayon serrurerie OVANIE Vendeur',
                      ),
                    ),
                    Positioned(
                      top: padding.top + 2,
                      left: 4,
                      child: IconButton(
                        onPressed: () => Navigator.of(context).maybePop(),
                        tooltip: 'Retour',
                        icon: const Icon(
                          Icons.arrow_back_ios_new_rounded,
                          color: Colors.white,
                          size: 24,
                          shadows: <Shadow>[Shadow(color: Color(0x99000000), blurRadius: 8)],
                        ),
                      ),
                    ),
                    Padding(
                      padding: EdgeInsets.fromLTRB(10, cardTop, 10, padding.bottom + 16),
                      child: Center(
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: Mk.maxWidth),
                          child: Stack(
                            clipBehavior: Clip.none,
                            alignment: Alignment.topCenter,
                            children: <Widget>[
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.fromLTRB(18, badge / 2 + 14, 18, 22),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(26),
                                  boxShadow: const <BoxShadow>[
                                    BoxShadow(color: Color(0x140A1B4F), blurRadius: 24, offset: Offset(0, 8)),
                                  ],
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.stretch,
                                  children: <Widget>[
                                    FittedBox(
                                      fit: BoxFit.scaleDown,
                                      child: Text(
                                        title,
                                        textAlign: TextAlign.center,
                                        style: const TextStyle(
                                          color: Mk.navy,
                                          fontSize: 25,
                                          height: 1.15,
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: -.6,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      subtitle,
                                      textAlign: TextAlign.center,
                                      style: const TextStyle(color: Mk.muted, fontSize: 14, height: 1.4, fontWeight: FontWeight.w500),
                                    ),
                                    const SizedBox(height: 20),
                                    ...children,
                                  ],
                                ),
                              ),
                              Positioned(
                                top: -badge / 2,
                                child: Container(
                                  width: badge,
                                  height: badge,
                                  decoration: BoxDecoration(
                                    color: sent ? const Color(0xFFE6F6EC) : Mk.navy,
                                    shape: BoxShape.circle,
                                    border: Border.all(color: Colors.white, width: 4),
                                  ),
                                  child: Icon(
                                    sent ? Icons.mark_email_unread_outlined : Icons.key_rounded,
                                    color: sent ? const Color(0xFF12894A) : Mk.orange,
                                    size: 32,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step({required this.number, required this.label});

  final String number;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Container(
          width: 28,
          height: 28,
          alignment: Alignment.center,
          decoration: const BoxDecoration(color: Mk.orange, shape: BoxShape.circle),
          child: Text(
            number,
            style: const TextStyle(color: Colors.white, fontSize: 13.5, fontWeight: FontWeight.w800),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              label,
              style: const TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
            ),
          ),
        ),
      ],
    );
  }
}
