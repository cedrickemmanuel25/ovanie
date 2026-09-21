import 'package:flutter/material.dart';

import 'offer_collection_screen.dart';

class BestSellersScreen extends StatelessWidget {
  final String? offer;

  const BestSellersScreen({super.key, this.offer});

  @override
  Widget build(BuildContext context) {
    return OvanieOfferCollectionScreen(
      kind: OvanieOfferKind.bestSellers,
      initialOffer: offer,
    );
  }
}
