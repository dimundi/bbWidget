# Shortcodes

BB Widget → **Shortcodes**: lista kodów, przycisk kopiowania i działające przykłady.

| Kod | Działanie |
| --- | --- |
| `[bbwidget]` | Komunikat testowy wtyczki. |

Nowe shortcode’y rejestrujemy przez `BBW_Shortcodes::register(tag, callback, tytuł, opis, przykład)`. Trafiają jednocześnie do WordPressa i do tej listy w panelu. Przykład jest wykonywany przez `do_shortcode`.
