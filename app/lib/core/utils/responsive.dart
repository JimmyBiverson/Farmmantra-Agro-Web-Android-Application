import 'package:flutter/material.dart';
import '../constants/app_constants.dart';

class Responsive {
  static bool isPhone(BuildContext context) =>
      MediaQuery.of(context).size.width < AppConstants.tabletBreakpoint;

  static bool isTablet(BuildContext context) {
    final width = MediaQuery.of(context).size.width;
    return width >= AppConstants.tabletBreakpoint &&
        width < AppConstants.desktopBreakpoint;
  }

  static bool isDesktop(BuildContext context) =>
      MediaQuery.of(context).size.width >= AppConstants.desktopBreakpoint;

  static bool isLargeDesktop(BuildContext context) =>
      MediaQuery.of(context).size.width >= AppConstants.largeDesktopBreakpoint;

  static double screenWidth(BuildContext context) =>
      MediaQuery.of(context).size.width;

  static double screenHeight(BuildContext context) =>
      MediaQuery.of(context).size.height;

  static int gridColumns(BuildContext context) {
    final width = screenWidth(context);
    if (width >= AppConstants.largeDesktopBreakpoint) return 4;
    if (width >= AppConstants.desktopBreakpoint) return 3;
    if (width >= AppConstants.tabletBreakpoint) return 2;
    return 1;
  }

  static double horizontalPadding(BuildContext context) {
    final width = screenWidth(context);
    if (width >= AppConstants.desktopBreakpoint) return 32.0;
    if (width >= AppConstants.tabletBreakpoint) return 24.0;
    return AppConstants.defaultPadding;
  }

  static double cardMaxWidth(BuildContext context) {
    final width = screenWidth(context);
    if (width >= AppConstants.largeDesktopBreakpoint) return 400.0;
    if (width >= AppConstants.desktopBreakpoint) return 350.0;
    return width;
  }
}
