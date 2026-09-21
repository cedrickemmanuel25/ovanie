import 'dart:math' as math;

import 'package:flutter/material.dart';

/// Breakpoints et calculs de mise en page communs à toute l'application OVANIE.
///
/// Toutes les valeurs sont exprimées en logical pixels Flutter. La résolution
/// physique (720p, 1080p, 2K...) et la densité Android ne servent jamais de
/// règle de disposition. Seul l'espace réellement disponible au widget compte.
class OvanieResponsive {
  OvanieResponsive._();

  static const double compactBreakpoint = 600;
  static const double tabletBreakpoint = 840;
  static const double desktopBreakpoint = 1200;
  static const double maxContentWidth = 1240;

  static Size size(BuildContext context) => MediaQuery.sizeOf(context);

  static bool isCompact(BuildContext context) =>
      size(context).width < compactBreakpoint;

  static bool isTablet(BuildContext context) {
    final width = size(context).width;
    return width >= compactBreakpoint && width < desktopBreakpoint;
  }

  static bool isWide(BuildContext context) =>
      size(context).width >= desktopBreakpoint;

  static bool isLandscape(BuildContext context) =>
      MediaQuery.orientationOf(context) == Orientation.landscape;

  /// Décision pure, testable, utilisée par le shell principal.
  static bool useNavigationRailForSize(Size viewport) {
    return viewport.width >= tabletBreakpoint ||
        (viewport.width >= 700 && viewport.width > viewport.height);
  }

  static bool useNavigationRail(BuildContext context) =>
      useNavigationRailForSize(size(context));

  /// Largeur professionnelle de la navigation latérale tablette.
  ///
  /// Les libellés Accueil, Catégories, Favoris, Panier et Compte restent
  /// toujours visibles tout en préservant suffisamment d'espace pour le
  /// contenu principal, y compris sur une tablette de largeur intermédiaire.
  static double tabletNavigationWidthForWidth(double width) {
    if (width < 900) return 184;
    if (width < desktopBreakpoint) return 204;
    return 224;
  }

  static double tabletNavigationWidth(BuildContext context) =>
      tabletNavigationWidthForWidth(size(context).width);

  static double horizontalPaddingForWidth(double width) {
    if (width < 360) return 12;
    if (width < compactBreakpoint) return 16;
    if (width < tabletBreakpoint) return 20;
    if (width < desktopBreakpoint) return 24;
    return 32;
  }

  static double horizontalPadding(BuildContext context) =>
      horizontalPaddingForWidth(size(context).width);

  /// Nombre de colonnes calculé à partir de la largeur *disponible* du parent.
  ///
  /// Important : sur tablette paysage, une NavigationRail peut réduire la
  /// largeur réelle du contenu. Les écrans de grille passent donc la valeur de
  /// LayoutBuilder.constraints.maxWidth plutôt que la largeur MediaQuery
  /// globale du terminal.
  static int productGridColumnsForWidth(double width) {
    final padding = horizontalPaddingForWidth(width);
    final usable = math.max(0.0, width - padding * 2);
    const spacing = 12.0;
    const targetTileWidth = 178.0;

    if (usable < 260) return 1;

    var columns = ((usable + spacing) / (targetTileWidth + spacing)).floor();

    // Un téléphone classique doit conserver deux cartes quand l'espace réel
    // le permet. Sous ~300 px (split-screen très étroit), une seule colonne
    // évite d'écraser le contenu.
    if (width >= 300 && width < 390) {
      columns = math.max(2, columns);
    }

    return columns.clamp(1, 6).toInt();
  }

  static int productGridColumns(BuildContext context) =>
      productGridColumnsForWidth(size(context).width);

  static double productTileWidthForWidth(double width, int columns) {
    final safeColumns = math.max(1, columns);
    final padding = horizontalPaddingForWidth(width);
    const spacing = 12.0;
    final value =
        (width - padding * 2 - spacing * (safeColumns - 1)) / safeColumns;
    return math.max(1.0, value);
  }

  static double horizontalProductCardWidthForWidth(double width) {
    if (width < 320) return math.max(136, width - 40);
    if (width < 360) return 150;
    if (width < compactBreakpoint) return 168;
    if (width < tabletBreakpoint) return 184;
    if (width < desktopBreakpoint) return 196;
    return 208;
  }

  static double categoryCardWidthForWidth(double width) {
    if (width < 320) return 82;
    if (width < 360) return 86;
    if (width < compactBreakpoint) return 96;
    if (width < tabletBreakpoint) return 108;
    return 118;
  }

  static double constrainedContentWidth(
    double viewportWidth, {
    double maxWidth = 820,
  }) =>
      math.min(viewportWidth, maxWidth);
}

class OvanieConstrainedBox extends StatelessWidget {
  final Widget child;
  final double maxWidth;
  final Alignment alignment;

  const OvanieConstrainedBox({
    super.key,
    required this.child,
    this.maxWidth = 820,
    this.alignment = Alignment.topCenter,
  });

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: alignment,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: child,
      ),
    );
  }
}
