import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/theme/app_colors.dart';
import 'api/api_service.dart';

/// Opens a printable document (order, delivery note, receipt, statement,
/// stock report) in the device browser, where it can be printed or saved
/// as PDF. The server checks access and returns a short-lived signed link.
class PrintService {
  static Future<void> open(
    BuildContext context,
    ApiService api,
    String type,
    String id, {
    Map<String, String>? query,
  }) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      final url = await api.getPrintLink(type, id, query: query);
      final launched = await launchUrl(
        Uri.parse(url),
        mode: LaunchMode.externalApplication,
      );
      if (!launched) {
        messenger.showSnackBar(
          const SnackBar(
            content: Text('Could not open the browser to print.'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } catch (e) {
      messenger.showSnackBar(
        SnackBar(
          content: Text('Print failed: ${e.toString().replaceFirst('Exception: ', '')}'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }
}
