plugins {
    alias(libs.plugins.android.application)
}

android {
    namespace = "com.quirky.ide"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.quirky.ide"
        minSdk = 24

        targetSdk = 28
        versionCode = 9
        versionName = "1.8.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"

        ndk {
            abiFilters += listOf("arm64-v8a")
        }
    }

    buildFeatures {
        buildConfig = true
    }

    sourceSets {
        getByName("main") {
            jniLibs.srcDirs("src/main/jniLibs")
        }
    }

    packaging {
        jniLibs {
            useLegacyPackaging = true
            // Intentionally NOT adding "**/*.so" to keepDebugSymbols here.
            // Leaving this empty lets Android Studio strip debug symbols
            // from your prebuilt .so files automatically when packaging
            // the APK — this is the setting that actually shrinks your
            // Termux/PHP libraries, since they're copied in via jniLibs
            // rather than compiled by this project's own NDK build.
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            ndk {
                // NONE = strip everything (smallest size, no crash symbolication)
                // SYMBOL_TABLE = smaller than FULL but still lets you read
                //   crash stack traces later, if that matters to you
                // Note: this setting mainly affects .so files THIS project
                // compiles from source (via CMake/ndk-build). Your prebuilt
                // Termux .so files are shrunk by the "packaging" block above.
                debugSymbolLevel = "NONE"
            }
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }
}

dependencies {
    implementation(libs.appcompat)
    implementation(libs.material)
    implementation(libs.activity)
    implementation(libs.constraintlayout)
    testImplementation(libs.junit)
    androidTestImplementation(libs.ext.junit)
    androidTestImplementation(libs.espresso.core)
}