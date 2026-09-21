allprojects {
    repositories {
        google()
        mavenCentral()

        // Dépôt officiel Mapbox pour les dépendances natives Android.
        // Depuis mapbox_maps_flutter 2.4+, aucun token secret Downloads:Read
        // n'est requis pour installer le SDK Flutter.
        maven {
            url = uri("https://api.mapbox.com/downloads/v2/releases/maven")
        }
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
subprojects {
    project.evaluationDependsOn(":app")
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
