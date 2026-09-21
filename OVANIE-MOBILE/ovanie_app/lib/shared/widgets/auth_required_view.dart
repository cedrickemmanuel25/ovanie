import 'package:flutter/material.dart';

import '../../app/theme.dart';

class AuthRequiredView extends StatelessWidget {
  final VoidCallback onOpenAccount;
  final EdgeInsetsGeometry padding;

  const AuthRequiredView({
    super.key,
    required this.onOpenAccount,
    this.padding = const EdgeInsets.fromLTRB(30, 40, 30, 80),
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: padding,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 360),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: OvanieColors.blue.withValues(alpha: 0.08),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.person_outline_rounded,
                  size: 34,
                  color: OvanieColors.blue,
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'Connectez-vous pour accéder à ce contenu',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: OvanieColors.text,
                  fontSize: 17,
                  height: 1.25,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Si vous n’êtes pas encore enregistré, créez votre compte avec votre adresse e-mail.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: OvanieColors.muted,
                  fontSize: 12.5,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                height: 46,
                child: FilledButton(
                  onPressed: onOpenAccount,
                  style: FilledButton.styleFrom(
                    backgroundColor: OvanieColors.orange,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text(
                    'Accéder au compte',
                    style: TextStyle(fontWeight: FontWeight.w900),
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
