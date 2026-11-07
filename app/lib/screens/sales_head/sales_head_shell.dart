import 'package:flutter/material.dart';
import '../../features/operations/presentation/operations_home.dart';

/// SalesHeadShell owns the role entry point; shared workflows live in features/operations.
class SalesHeadShell extends StatelessWidget {
  const SalesHeadShell({super.key});
  @override
  Widget build(BuildContext context) => const OperationsShell();
}
