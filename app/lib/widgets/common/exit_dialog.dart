import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

Future<bool> confirmExit(BuildContext context) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: const Text('Leave app?'),
      content: const Text(
        'Are you sure you want to close the app? You will need to log in again when you open it.',
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(dialogContext, false),
          child: const Text('Stay'),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(dialogContext, true),
          style: FilledButton.styleFrom(backgroundColor: AppColors.error),
          child: const Text('Close app'),
        ),
      ],
    ),
  );
  return result ?? false;
}
