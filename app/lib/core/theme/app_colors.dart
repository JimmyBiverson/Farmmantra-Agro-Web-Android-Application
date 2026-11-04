import 'package:flutter/material.dart';

class AppColors {
  AppColors._();

  // Primary brand colors
  static const Color primaryGreen = Color(0xFF27744D);
  static const Color primaryGreenLight = Color(0xFFEAF3E9);
  static const Color primaryGreenDark = Color(0xFF123B2D);

  // Accent
  static const Color accentGold = Color(0xFFC5DC9B);
  static const Color accentGoldLight = Color(0xFFFDD835);

  // Backgrounds
  static const Color backgroundLight = Color(0xFFF5F7F4);
  static const Color backgroundWhite = Colors.white;
  static const Color surfaceWhite = Colors.white;

  // Text
  static const Color textPrimary = Color(0xFF20372B);
  static const Color textSecondary = Color(0xFF6B7280);
  static const Color textLight = Color(0xFF9CA3AF);
  static const Color textOnPrimary = Colors.white;

  // Status colors
  static const Color success = Color(0xFF10B981);
  static const Color warning = Color(0xFFF59E0B);
  static const Color error = Color(0xFFEF4444);
  static const Color info = Color(0xFF3B82F6);

  // Order status colors
  static const Color statusPending = Color(0xFFFFA726);
  static const Color statusApproved = Color(0xFF66BB6A);
  static const Color statusDeclined = Color(0xFFEF5350);
  static const Color statusDelivered = Color(0xFF42A5F5);
  static const Color statusAdjusted = Color(0xFFAB47BC);

  // Payment status colors
  static const Color paymentPending = Color(0xFFFFA726);
  static const Color paymentVerified = Color(0xFF42A5F5);
  static const Color paymentAccepted = Color(0xFF66BB6A);
  static const Color paymentRejected = Color(0xFFEF5350);

  // Divider
  static const Color divider = Color(0xFFE5E7EB);
  static const Color border = Color(0xFFD1D5DB);

  // Misc
  static const Color shimmer = Color(0xFFE0E0E0);
  static const Color shadow = Color(0x1A000000);
}
