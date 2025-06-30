// Include the DHT11 library for interfacing with the sensor.
#include <DHT11.h>
//include wifi library
#include <WiFi.h>
//include http communication library
#include <HTTPClient.h>
//include json library
#include <ArduinoJson.h>
//for the screen
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
//sensors
const int photoDiode = 4;
const int tempSensor = 5;
//pump
const int pump = 6;
//for humidity sensors, their names, number, initializing objects

const int numOfSensors = 1;
String sensorNames[numOfSensors] = { "Sensor1" };
int sensorPins[numOfSensors] = { 2 };
DHT11 sensors[numOfSensors] = { DHT11(sensorPins[0]) };
int readPins[numOfSensors] = { 3 };
//analog read resolution
int AnalogReadResolution = 10;
//struct for linking sensor and their value
struct Entry {
  String key;
  int value;
};
Entry mapTable[] = {
  { "Sensor1", 63 }
};
//wifi credentials
const char* ssid = "Vodafone_2.4G-00680";
const char* password = "nNge35ztHznySjUh";
const char* serverURL = "http://192.168.2.7/irigacio_automata/";
String get_wifi_status(int status) {
  switch (status) {
    case WL_IDLE_STATUS:
      return "WL_IDLE_STATUS";
    case WL_SCAN_COMPLETED:
      return "WL_SCAN_COMPLETED";
    case WL_NO_SSID_AVAIL:
      return "WL_NO_SSID_AVAIL";
    case WL_CONNECT_FAILED:
      return "WL_CONNECT_FAILED";
    case WL_CONNECTION_LOST:
      return "WL_CONNECTION_LOST";
    case WL_CONNECTED:
      return "WL_CONNECTED";
    case WL_DISCONNECTED:
      return "WL_DISCONNECTED";
  }
}
//display definitions
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, -1);
void setup() {
  Serial.begin(9600);
  // initiate display
  if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    Serial.println(F("SSD1306 allocation failed"));
  }

  analogReadResolution(AnalogReadResolution);
  for (int j = 0; j < sizeof(sensors) / sizeof(sensors[0]); j++) {
    pinMode(sensorPins[j], INPUT);
  }
  pinMode(photoDiode, INPUT);
  pinMode(tempSensor, INPUT);
  pinMode(pump, OUTPUT);
  // dht11.setDelay(500); // Set this to the desired delay. Default is 500ms.
  //setting up wifi
  WiFi.begin(ssid, password);
  Serial.println("\nConnecting");

  while (WiFi.status() != WL_CONNECTED) {
    int status = WiFi.status();
    
    delay(100);
  }

  Serial.println("\nConnected to the WiFi network");
  Serial.print("Local ESP32 IP: ");
  Serial.println(WiFi.localIP());
}

void loop() {
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("HTTP comunication started");
    Serial.println(get_wifi_status(status));
    displayFunc(get_wifi_status(status), 0, 0, 1);
    HTTPClient http;
    http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);
    http.begin(serverURL);
    http.addHeader("Content-Type", "application/json");
    for (int j = 0; j < sizeof(sensors) / sizeof(sensors[0]); j++) {
      int sensorValue = adcCorrect(analogLoading(readPins[j]));
      Serial.print("analog: ");
      Serial.println(sensorValue);
      String sensorOutput = readSensor(sensorValue, getValue(sensorNames[j]), sensors[j].readHumidity());
      Serial.print("humidity: ");
      Serial.println(sensorOutput);
      if (sensorOutput.length() > 3) {
        continue;
      }

      float temperature = temp();
      int sunlight = analogRead(photoDiode);
      float roundTemp = roundf(temperature * 10) / 10.0;


      // Create JSON payload
      DynamicJsonDocument doc(256);
      doc["s_value"] = sensorValue;
      doc["humidity"] = sensorOutput;
      doc["temperature"] = roundTemp;
      doc["sunlight"] = sunlight;

      String payload;
      serializeJson(doc, payload);

      // Send POST request
      int httpCode = http.POST(payload);

      if (httpCode == HTTP_CODE_OK) {
        String response = http.getString();
        Serial.println("Server Response: " + response);
        StaticJsonDocument<512> doc;
        DeserializationError error = deserializeJson(doc, response);
        if (!error) {
          int min_hum = doc["min_humidity"];
          int max_hum = doc["max_humidity"];
          Serial.println(min_hum);
          Serial.println(max_hum);
          int time = minutesOpen(sensorOutput.toInt(), roundTemp, min_hum, max_hum, sunlight);
          Serial.println(time);
          if (time > 0) {
            Serial.println(time);
            digitalWrite(pump, HIGH);
            delay(time);
            digitalWrite(pump, LOW);
          }
        }
      } else {
        // Enhanced error handling
        Serial.printf("HTTP Error code: %d\n", httpCode);
        Serial.printf("HTTP Error message: %s\n", http.errorToString(httpCode).c_str());

        // Get and print the server's response body
        String serverResponse = http.getString();
        if (serverResponse.length() > 0) {
          Serial.println("Server response body:");
          Serial.println(serverResponse);
        } else {
          Serial.println("(No additional error information from server)");
        }
      }
      http.end();
    }
    delay(10000);  // Send every 10 sec
  }
}



//measure temperature
float temp() {
  float vout = analogLoading(tempSensor);
  return ((vout * 3300) / 1023);
}
//for adc correction
int adcCorrect(int reading) {
  for (int i = 0; i < sizeof(sensors) / sizeof(sensors[0]); i++) {
    if (abs(reading - getValue(sensorNames[i])) <= 5) {
      return (getValue(sensorNames[i]));
    }
  }
}

//check the sensor number
int analogLoading(int pin) {
  int multisampling = 0;
  int result = 0;

  for (int i = 0; i < 70000; i++) {

    multisampling = (multisampling + analogRead(pin));
  }
  result = (multisampling / 700000);


  return (result);
}
//read the sensor
String readSensor(int result, int sensorValue, int humidity) {
  if (result == sensorValue) {

    // Check the result of the reading.
    // If there's no error, print the humidity value.
    // If there's an error, print the appropriate error message.
    if (humidity != DHT11::ERROR_CHECKSUM && humidity != DHT11::ERROR_TIMEOUT) {
      return (String(humidity));
    }
    // Print error message based on the error code.
    return (DHT11::getErrorString(humidity));

  } else {
    return ("");
  }
}
//get the value from the struct
int getValue(String key) {
  int n = sizeof(mapTable) / sizeof(mapTable[0]);
  for (int i = 0; i < n; i++) {
    if (mapTable[i].key == key) {
      return mapTable[i].value;
    }
  }
  return -1;  // not found
}
int minutesOpen(int currentHumidity, float currentTemp, int minHumidity, int maxHumidity, int sunLight) {
  if (currentHumidity > maxHumidity) {
    return 0;
  }
  //factors 0.0 - 1.0
  // Serial.println(maxHumidity);
  float lightFactor = sunLight / 1023.0;
  float tempFactor = currentTemp / 30.0;
  //pumpflow l/m
  int pumpFlow = 4;
  //how much humidity we need
  int mean = (maxHumidity + minHumidity) / 2;
  int deficit = mean - currentHumidity;
  //the .005 is a factor based on the plants and soild type
  float waterLiters = deficit * 0.3 * tempFactor * lightFactor;
  //the need for liters
  float pumpTimeMinutes = waterLiters / pumpFlow;
  Serial.println(String(lightFactor) + " " + String(tempFactor) + " " + String(deficit) + " " + String(waterLiters) + " " + String(pumpTimeMinutes));
  return (pumpTimeMinutes * 60 * 1000);
}

//function to output text
void displayFunc(String text, int posA, int posB, int textSize) {
  display.clearDisplay();

  display.setTextSize(textSize);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(posA, posB);
  display.println(text);

  display.display();  // Push buffer to screen
}
