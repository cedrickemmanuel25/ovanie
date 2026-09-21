import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_design.dart';
import '../../data/vendor_repository.dart';
import 'login_screen.dart';

class ResetPasswordScreen extends StatefulWidget {
  final String token;
  final String email;

  const ResetPasswordScreen({
    super.key,
    required this.token,
    required this.email,
  });

  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  final _form = GlobalKey<FormState>();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _showPassword = false;
  bool _showConfirmation = false;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_form.currentState?.validate() ?? false)) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await VendorRepository.instance.resetPassword(
        email: widget.email,
        token: widget.token,
        password: _password.text,
        confirmation: _confirmation.text,
      );
      if (!mounted) return;

      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (dialogContext) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
          icon: const Icon(Icons.check_circle_rounded, color: Color(0xFF15934E), size: 44),
          title: const Text(
            'Mot de passe modifié',
            textAlign: TextAlign.center,
            style: TextStyle(color: vendorText, fontWeight: FontWeight.w900),
          ),
          content: const Text(
            'Votre nouveau mot de passe est maintenant valable sur le Web et dans l’application Vendeur.',
            textAlign: TextAlign.center,
          ),
          actionsAlignment: MainAxisAlignment.center,
          actions: [
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: vendorOrange),
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Se connecter'),
            ),
          ],
        ),
      );

      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
        (_) => false,
      );
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 18, 24, 32),
            child: Form(
              key: _form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Align(
                    alignment: Alignment.centerLeft,
                    child: VendorBackButton(onPressed: () => Navigator.maybePop(context)),
                  ),
                  const SizedBox(height: 22),
                  const VendorCircleIcon(
                    icon: Icons.lock_reset_rounded,
                    color: vendorOrange,
                    background: vendorNavyDeep,
                    size: 72,
                    iconSize: 34,
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'Réinitialiser le mot de passe',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: vendorText, fontSize: 27, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Choisissez un nouveau mot de passe sécurisé pour votre compte vendeur.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: vendorMuted, fontSize: 14.5, height: 1.45),
                  ),
                  const SizedBox(height: 26),
                  VendorSheet(
                    radius: 22,
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        TextFormField(
                          initialValue: widget.email,
                          readOnly: true,
                          decoration: vendorInputDecoration(
                            hint: 'Adresse e-mail',
                            icon: Icons.email_outlined,
                          ),
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          controller: _password,
                          obscureText: !_showPassword,
                          enabled: !_saving,
                          decoration: vendorInputDecoration(
                            hint: 'Nouveau mot de passe',
                            icon: Icons.lock_outline_rounded,
                          ).copyWith(
                            suffixIcon: IconButton(
                              onPressed: _saving ? null : () => setState(() => _showPassword = !_showPassword),
                              icon: Icon(_showPassword ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                            ),
                          ),
                          validator: (value) => (value ?? '').length < 8
                              ? 'Minimum 8 caractères.'
                              : null,
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          controller: _confirmation,
                          obscureText: !_showConfirmation,
                          enabled: !_saving,
                          decoration: vendorInputDecoration(
                            hint: 'Confirmer le mot de passe',
                            icon: Icons.lock_outline_rounded,
                          ).copyWith(
                            suffixIcon: IconButton(
                              onPressed: _saving ? null : () => setState(() => _showConfirmation = !_showConfirmation),
                              icon: Icon(_showConfirmation ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                            ),
                          ),
                          validator: (value) => value != _password.text
                              ? 'Les mots de passe ne correspondent pas.'
                              : null,
                        ),
                        if (_error != null) ...[
                          const SizedBox(height: 12),
                          VendorErrorBox(_error),
                        ],
                        const SizedBox(height: 22),
                        VendorPrimaryButton(
                          label: _saving ? 'Réinitialisation…' : 'Réinitialiser le mot de passe',
                          onPressed: _submit,
                          loading: _saving,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.verified_user_outlined, color: Color(0xFF073FE2), size: 24),
                      SizedBox(width: 8),
                      Text('Réinitialisation sécurisée OVANIE', style: TextStyle(color: vendorMuted, fontSize: 13.5)),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
