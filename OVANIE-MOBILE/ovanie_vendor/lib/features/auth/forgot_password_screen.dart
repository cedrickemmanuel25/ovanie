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

    return MkCardAuthScaffold(
      icon: sent ? Icons.mark_email_read_rounded : Icons.key_rounded,
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
              decoration: mkInput(hint: 'exemple@domaine.com', icon: Icons.mail_outline_rounded),
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
      MkNotice(_success!, success: true),
      const SizedBox(height: 18),
      Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: Mk.softBlue, borderRadius: BorderRadius.circular(12)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Text(
              'Envoyé à',
              style: TextStyle(color: Mk.muted, fontSize: 12.5, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 4),
            Text(
              _identifier.text.trim().toLowerCase(),
              style: const TextStyle(color: Mk.navy, fontSize: 16, fontWeight: FontWeight.w800),
            ),
          ],
        ),
      ),
      const SizedBox(height: 20),
      const Text(
        'Et maintenant ?',
        style: TextStyle(color: Mk.navy, fontSize: 16, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 14),
      const _Step(number: '1', label: 'Ouvrez l’e-mail reçu d’OVANIE (pensez aux courriers indésirables).'),
      const SizedBox(height: 12),
      const _Step(number: '2', label: 'Touchez le lien : il ouvre directement cette application.'),
      const SizedBox(height: 12),
      const _Step(number: '3', label: 'Choisissez votre nouveau mot de passe.'),
      const SizedBox(height: 24),
      MkButton(
        label: 'Retour à la connexion',
        arrow: false,
        onPressed: () => Navigator.of(context).maybePop(),
      ),
      const SizedBox(height: 4),
      Center(
        child: TextButton(
          onPressed: _sending ? null : () => setState(() => _success = null),
          style: TextButton.styleFrom(
            foregroundColor: Mk.blue,
            minimumSize: const Size(48, 48),
            textStyle: const TextStyle(fontFamily: Mk.font, fontSize: 14.5, fontWeight: FontWeight.w700),
          ),
          child: const Text('Utiliser une autre adresse'),
        ),
      ),
    ];
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
          decoration: const BoxDecoration(color: Mk.softBlue, shape: BoxShape.circle),
          child: Text(
            number,
            style: const TextStyle(color: Mk.blue, fontSize: 13.5, fontWeight: FontWeight.w800),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              label,
              style: const TextStyle(color: Mk.navy, fontSize: 14.5, height: 1.4, fontWeight: FontWeight.w500),
            ),
          ),
        ),
      ],
    );
  }
}
