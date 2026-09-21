import 'package:flutter/material.dart';
import '../../app/theme.dart';
import 'ovanie_widgets.dart';

class DriverHero extends StatelessWidget {
  const DriverHero({
    super.key,
    required this.height,
    required this.showWordmark,
    required this.showTagline,
    required this.alignment,
  });
  final double height;
  final bool showWordmark;
  final bool showTagline;
  final CrossAxisAlignment alignment;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      ClipPath(
        clipper: _RoadCurve(),
        child: Image.asset(
          'assets/ovanie_truck_road.png',
          height: height,
          width: double.infinity,
          fit: BoxFit.cover,
          alignment: const Alignment(.25, .2),
          semanticLabel: 'Camion OVANIE Livreur sur une route bordée d’arbres',
        ),
      ),
      if (showWordmark || showTagline)
        Padding(
          padding: const EdgeInsets.fromLTRB(24, 0, 24, 20),
          child: Column(
            crossAxisAlignment: alignment,
            children: [
              if (showWordmark)
                OvanieWordmark(fontSize: 32, crossAxisAlignment: alignment),
              if (showTagline) ...[
                const SizedBox(height: 7),
                const Text(
                  'Chaque livraison\nrapproche les hommes.',
                  style: TextStyle(
                    color: OvanieColors.greenDark,
                    fontSize: 14,
                    height: 1.3,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ],
          ),
        ),
    ],
  );
}

class _RoadCurve extends CustomClipper<Path> {
  @override
  Path getClip(Size size) => Path()
    ..lineTo(0, size.height - 25)
    ..quadraticBezierTo(
      size.width * .12,
      size.height - 60,
      size.width * .35,
      size.height - 25,
    )
    ..cubicTo(
      size.width * .52,
      size.height + 8,
      size.width * .65,
      size.height,
      size.width,
      size.height,
    )
    ..lineTo(size.width, 0)
    ..close();
  @override
  bool shouldReclip(_RoadCurve oldClipper) => false;
}

class DriverProgress extends StatelessWidget {
  const DriverProgress({super.key, required this.step, required this.total});
  final int step;
  final int total;
  static const labels = [
    'Informations\npersonnelles',
    'Zones\nd’intervention',
    'Disponibilités',
    'Véhicule',
    'Récapitulatif',
  ];
  @override
  Widget build(BuildContext context) => Semantics(
    label: 'Étape $step sur $total',
    child: Column(
      children: [
        Row(
          children: [
            for (var i = 1; i <= total; i++)
              Expanded(
                child: Row(
                  children: [
                    Expanded(
                      child: Container(
                        height: 2,
                        color: i == 1
                            ? Colors.transparent
                            : i <= step
                            ? OvanieColors.green
                            : OvanieColors.border,
                      ),
                    ),
                    Container(
                      width: 30,
                      height: 30,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: i <= step
                            ? OvanieColors.green
                            : const Color(0xFFABB3BA),
                        shape: BoxShape.circle,
                      ),
                      child: i < step
                          ? const Icon(
                              Icons.check_rounded,
                              color: Colors.white,
                              size: 20,
                            )
                          : Text(
                              '$i',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                              ),
                            ),
                    ),
                    Expanded(
                      child: Container(
                        height: 2,
                        color: i == total
                            ? Colors.transparent
                            : i < step
                            ? OvanieColors.green
                            : OvanieColors.border,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (var i = 1; i <= total; i++)
              Expanded(
                child: Text(
                  i <= labels.length ? labels[i - 1] : '$i',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 9,
                    height: 1.3,
                    color: i <= step ? OvanieColors.text : OvanieColors.muted,
                    fontWeight: i == step ? FontWeight.w800 : FontWeight.w500,
                  ),
                ),
              ),
          ],
        ),
      ],
    ),
  );
}

class DriverStatusJourney extends StatelessWidget {
  const DriverStatusJourney({super.key});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: OvanieColors.greenLight,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Column(
      children: [
        for (final item in [
          (
            Icons.check_circle_rounded,
            'Dossier envoyé',
            'Vos informations ont bien été reçues.',
            true,
          ),
          (
            Icons.schedule_rounded,
            'Vérification en cours',
            'Notre équipe examine vos documents.',
            true,
          ),
          (
            Icons.local_shipping_outlined,
            'Prêt à livrer',
            'Après validation de votre compte.',
            false,
          ),
        ])
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 9),
            child: Row(
              children: [
                Icon(
                  item.$1,
                  color: item.$4 ? OvanieColors.green : OvanieColors.muted,
                  size: 24,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.$2,
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                          color: item.$4
                              ? OvanieColors.text
                              : OvanieColors.muted,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        item.$3,
                        style: const TextStyle(
                          color: OvanieColors.muted,
                          fontSize: 12,
                          height: 1.4,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}
