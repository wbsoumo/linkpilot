import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/main.dart';

void main() {
  testWidgets('Smoke test for ProviderScope and LinkPilotApp', (WidgetTester tester) async {
    // Build our app and trigger a frame.
    await tester.pumpWidget(
      const ProviderScope(
        child: LinkPilotApp(),
      ),
    );

    // Verify the widget tree compiles and builds without error
    expect(find.byType(ProviderScope), findsOneWidget);
  });
}
