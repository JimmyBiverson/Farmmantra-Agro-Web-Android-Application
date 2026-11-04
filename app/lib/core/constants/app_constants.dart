class AppConstants {
  AppConstants._();

  static const String appName = 'Farmmantra';
  static const String appFullName = 'Farmmantra Agro Chemicals Limited';
  static const String appTagline = 'Agricultural Distribution Platform';

  static const Duration splashDuration = Duration(seconds: 2);
  static const Duration animationDuration = Duration(milliseconds: 300);
  static const Duration apiTimeout = Duration(seconds: 30);
  static const Duration debounceDuration = Duration(milliseconds: 500);

  static const int defaultPageSize = 20;
  static const int maxUploadSizeMB = 10;
  static const int passwordMinLength = 6;

  static const double tabletBreakpoint = 768.0;
  static const double desktopBreakpoint = 1024.0;
  static const double largeDesktopBreakpoint = 1440.0;

  static const double defaultPadding = 16.0;
  static const double smallPadding = 8.0;
  static const double largePadding = 24.0;
  static const double defaultBorderRadius = 12.0;
  static const double smallBorderRadius = 8.0;
  static const double cardElevation = 2.0;

  static const String dateFormat = 'dd MMM yyyy';
  static const String dateTimeFormat = 'dd MMM yyyy, hh:mm a';
  static const String currencyFormat = 'UGX';
}
