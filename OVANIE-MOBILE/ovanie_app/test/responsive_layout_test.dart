import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_app/app/theme.dart';
import 'package:ovanie_app/core/layout/responsive.dart';
import 'package:ovanie_app/features/products/domain/product_model.dart';
import 'package:ovanie_app/shared/widgets/product_card.dart';

void main() {
  final product = ProductModel.fromJson({
    'id': 1,
    'slug': 'chaussures-securite',
    'name': 'Chaussures de sécurité montantes professionnelles',
    'category_name': 'Outillage & équipements',
    'category_slug': 'outillage-equipements',
    'price': 23310,
    'final_price': 23310,
    'stock': 12,
    'can_add_to_cart': true,
    'is_orderable': true,
    'availability_label': 'En stock',
    'reviews_count': 0,
  });

  test('product grid columns adapt to available logical width', () {
    expect(OvanieResponsive.productGridColumnsForWidth(280), 1);
    expect(OvanieResponsive.productGridColumnsForWidth(320), 2);
    expect(OvanieResponsive.productGridColumnsForWidth(360), 2);
    expect(
      OvanieResponsive.productGridColumnsForWidth(480),
      greaterThanOrEqualTo(2),
    );
    expect(
      OvanieResponsive.productGridColumnsForWidth(800),
      greaterThanOrEqualTo(3),
    );
    expect(
      OvanieResponsive.productGridColumnsForWidth(1200),
      greaterThanOrEqualTo(5),
    );
  });

  test('tablet navigation width remains readable without crushing content', () {
    expect(OvanieResponsive.tabletNavigationWidthForWidth(840), 184);
    expect(OvanieResponsive.tabletNavigationWidthForWidth(1024), 204);
    expect(OvanieResponsive.tabletNavigationWidthForWidth(1280), 224);
  });

  test('navigation changes with tablet orientation / available size', () {
    // Téléphone portrait type Galaxy A04.
    expect(
      OvanieResponsive.useNavigationRailForSize(const Size(360, 800)),
      isFalse,
    );

    // Galaxy Tab A8 portrait : barre inférieure conservée si la largeur utile
    // reste inférieure au breakpoint rail.
    expect(
      OvanieResponsive.useNavigationRailForSize(const Size(800, 1280)),
      isFalse,
    );

    // Tablette paysage : rail latéral pour libérer la hauteur utile.
    expect(
      OvanieResponsive.useNavigationRailForSize(const Size(1280, 800)),
      isTrue,
    );
  });

  const deviceMatrix = <Size>[
    Size(280, 640), // split-screen / très compact
    Size(320, 720),
    Size(360, 800), // téléphone type A04
    Size(411, 891),
    Size(600, 960),
    Size(800, 1280), // tablette type Tab A8 portrait
    Size(1280, 800), // tablette paysage
    Size(1440, 900),
  ];

  for (final size in deviceMatrix) {
    for (final textScale in <double>[1.0, 1.3, 1.8, 2.2]) {
      testWidgets(
        'ProductCard no RenderFlex overflow at ${size.width}x${size.height}, text $textScale',
        (tester) async {
          await tester.binding.setSurfaceSize(size);
          addTearDown(() => tester.binding.setSurfaceSize(null));

          final columns =
              OvanieResponsive.productGridColumnsForWidth(size.width);
          final tileWidth = OvanieResponsive.productTileWidthForWidth(
            size.width,
            columns,
          );

          await tester.pumpWidget(
            MaterialApp(
              theme: OvanieTheme.light,
              home: MediaQuery(
                data: MediaQueryData(
                  size: size,
                  textScaler: TextScaler.linear(textScale),
                ),
                child: Scaffold(
                  body: Builder(
                    builder: (context) {
                      final extent = ProductCard.extentForWidth(
                        context,
                        width: tileWidth,
                      );
                      return Align(
                        alignment: Alignment.topLeft,
                        child: SizedBox(
                          width: tileWidth,
                          height: extent,
                          child: ProductCard(product: product),
                        ),
                      );
                    },
                  ),
                ),
              ),
            ),
          );

          await tester.pump();
          expect(tester.takeException(), isNull);
        },
      );
    }
  }
}
