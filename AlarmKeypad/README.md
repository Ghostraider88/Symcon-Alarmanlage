# AlarmKeypad

PIN-Tastenfeld für die **Kachelvisualisierung** von IP-Symcon, das direkt mit der
[SymconAlarmPro](../SymconAlarmPro/README.md)-Alarmzentrale zusammenarbeitet.

Im Gegensatz zu einem reinen PIN-Pad, das nur eine PIN in eine Variable schreibt und damit
immer denselben Befehl (Unscharf) auslöst, besitzt das AlarmKeypad eigene **Befehlstasten**.
Die eingegebene PIN wird zusammen mit dem gewählten Befehl an die Alarmzentrale gesendet –
so lässt sich auch bei aktivem PIN-Schutz gezielt scharfschalten, quittieren oder zurücksetzen.

---

## Funktionen

- Numpad (0–9, `clear`, Rücktaste) im Stil der Kachelvisu
- Fünf Befehlstasten mit Icons:
  - 🌙 **Nacht** → `Alarm_ArmNight`
  - 🔒 **Scharf** → `Alarm_ArmAway`
  - 🔓 **Unscharf** → `Alarm_Disarm`
  - ✓ **Quitt.** → `Alarm_Acknowledge`
  - ↺ **Reset** → `Alarm_Reset`
- Direkte Rückmeldung in der Kachel (grün = erfolgreich, rot = abgelehnt/Fehler)
- Automatische Anpassung an helles/dunkles Tile-Theme über die Kachel-Parameter

---

## Einrichtung

1. Bibliothek über die **Modulverwaltung** hinzufügen (siehe Haupt-README)
2. Neue Instanz **AlarmKeypad** anlegen
3. In der Konfiguration die **Alarmzentrale (Instanz)** auswählen
4. Die Kachel der Instanz in der Visualisierung platzieren

Die eingegebene PIN wird an die Alarmzentrale weitergereicht und dort geprüft. Die PIN selbst
wird im AlarmKeypad **nicht** gespeichert.

---

## Konfiguration

| Feld | Beschreibung |
|------|--------------|
| Alarmzentrale (Instanz) | Die SymconAlarmPro-Instanz, die gesteuert werden soll |

Ob für eine Aktion eine PIN nötig ist, wird **ausschließlich in der Alarmzentrale**
(SymconAlarmPro → *PIN & Sicherheit*) festgelegt. Das AlarmKeypad besitzt dafür keine eigene
Einstellung – es reicht die eingegebene PIN durch, die Prüfung übernimmt die Alarmzentrale.

---

## Kompatibilität

- IP-Symcon 8.1 oder neuer
- Verwendet `IPSModuleStrict`
- Benötigt eine konfigurierte **SymconAlarmPro**-Instanz

---

## Danksagung

Dieses Modul basiert auf dem Pinpad-Modul von **lorbetzki**
([net.lorbetzki.pinpad](https://github.com/lorbetzki/net.lorbetzki.pinpad)).
Layout und JavaScript-Anbindung der Kachel (Numpad, Theming über `queryParameters`,
`handleMessage`/`requestAction`) wurden von dort übernommen und um die Befehlstasten erweitert.
Die Verwendung erfolgt mit ausdrücklicher Genehmigung des Autors – vielen Dank an lorbetzki.
