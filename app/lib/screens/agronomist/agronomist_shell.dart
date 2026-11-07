import 'package:flutter/material.dart';
import '../../features/operations/presentation/operations_home.dart';

/// AgronomistShell owns the role entry point; shared workflows live in features/operations.
class AgronomistShell extends StatelessWidget {
  const AgronomistShell({super.key});
  @override
  Widget build(BuildContext context) => const OperationsShell();
}
