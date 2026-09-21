import 'package:flutter/material.dart';

import 'offer_collection_screen.dart';

class NewArrivalsScreen extends StatelessWidget {
  final String? offer;

  const NewArrivalsScreen({super.key, this.offer});

  @override
  Widget build(BuildContext context) {
    return OvanieOfferCollectionScreen(
      kind: OvanieOfferKind.latest,
      initialOffer: offer,
    );
  }
}
