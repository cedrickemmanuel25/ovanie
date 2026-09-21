import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ovanie_vendor/core/layout/responsive.dart';

void main() {
  test('vendor grids adapt to real available logical width', () {
    expect(OvanieResponsive.productGridColumnsForWidth(280), 1);
    expect(OvanieResponsive.productGridColumnsForWidth(320), 2);
    expect(OvanieResponsive.productGridColumnsForWidth(360), 2);
    expect(OvanieResponsive.productGridColumnsForWidth(800), greaterThanOrEqualTo(3));
    expect(OvanieResponsive.productGridColumnsForWidth(1280), greaterThanOrEqualTo(5));
  });

  test('tablet vendor navigation is lateral only when the viewport warrants it', () {
    expect(OvanieResponsive.useNavigationRailForSize(const Size(360, 800)), isFalse);
    expect(OvanieResponsive.useNavigationRailForSize(const Size(800, 1280)), isFalse);
    expect(OvanieResponsive.useNavigationRailForSize(const Size(1280, 800)), isTrue);
  });

  test('professional vendor rail has readable tablet widths', () {
    expect(OvanieResponsive.tabletNavigationWidthForWidth(840), 184);
    expect(OvanieResponsive.tabletNavigationWidthForWidth(1024), 204);
    expect(OvanieResponsive.tabletNavigationWidthForWidth(1280), 224);
  });
}
