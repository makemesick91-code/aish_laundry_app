package id.aishtech.laundry.ops;

import androidx.test.rule.ActivityTestRule;
import dev.flutter.plugins.integration_test.FlutterTestRunner;
import org.junit.Rule;
import org.junit.runner.RunWith;

/**
 * Entry point that lets `adb shell am instrument` drive the Dart
 * `integration_test` suites on a device.
 *
 * <p>This exists so an on-device run can happen WITHOUT `flutter test`, which
 * uninstalls the application between invocations and therefore wipes app data —
 * including the EncryptedSharedPreferences that back the Android Keystore
 * store. Proving that a session survives an application restart requires two
 * launches of the SAME installed package, and that is only reachable through
 * instrumentation.
 */
@RunWith(FlutterTestRunner.class)
public class MainActivityTest {
    @Rule
    public ActivityTestRule<MainActivity> rule =
            new ActivityTestRule<>(MainActivity.class, true, false);
}
