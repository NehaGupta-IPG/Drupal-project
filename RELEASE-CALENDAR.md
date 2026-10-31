# Drupal Release Calendar

[View it in GitLab](https://git.drupalcode.org/project/drupal/-/blob/11.x/RELEASES-CALENDAR.md)

```mermaid
---
displayMode: compact
---
%% (`Green for fully support, orange for security, yellow for maintenance.`)
%%{
  init: {
    'theme': 'base',
    'themeVariables': {
      'primaryColor': '#75c831',
      'secondaryColor': '#00ff00',
      'tertiaryTextColor': '#000000',
      'tertiaryColor': '#75c831',
      'doneTaskBkgColor': '#ffbd1f',
      'critBkgColor': '#fae52f',
      'critBorderColor': '#807304',
      'textColor': '#000000'
    }
  }
}%%

gantt
    title Drupal Release Calendar
    dateFormat  YYYY-MM
    axisFormat %Y %b
    todayMarker off
    section Drupal 10.2
    10.2 Security   :done, 102sec, 2024-06, 6M
    section Drupal 10.3
    10.3 Support    :103sup, 2024-06, 6M
    10.3 Security   :done, 103sec, after 103sup,6M
    section Drupal 10.4
    10.4 Maint      :crit, 104mnt, after 103sup, 6M
    10.4 Security   :done, 104sec, after 104mnt, 6M
    section Drupal 10.5
    10.5 Maint      :crit, 105mnt, after 104mnt, 6M
    10.5 Security   :done, 105sec, after 105mnt, 6M
    section Drupal 11.0
    11.0 Support    :110sup, 2024-06  , 6M
    11.0 Security   :done, 110sec, after 110sup, 6M
    section Drupal 11.1
    11.1 Support    :111sup, after 110sup, 6M
    11.1 Security   :done, 111sec, after 111sup, 6M
    section Drupal 11.2
    11.2 Support    :112sup, after 111sup, 6M
    11.2 Security   :done, 112sec, after 112sup, 6M
    section Drupal 11.3
    11.3 Support    :113sup, after 112sup, 6M
    11.3 Security   :done, 113sec, after 113sup, 6M
    section Drupal 11.4
    11.4 Support    :114sup, after 113sup, 6M
    11.4 Security   :done, 114sec, after 114sup, 6M
    section Drupal 11.5
    11.5 Maint      :crit, 115mnt, after 114sup, 6M
    11.5 Security   :done, 115sec, after 115mnt, 6M
    section Drupal 11.6
    11.6 Maint      :crit, 116mnt, after 115mnt, 6M
    section Drupal 12.0
    12.0 Support    :120sup, after 113sup, 6M
    12.0 Security   :done, 120sec, after 120sup, 6M
    section Drupal 12.1
    12.1 Support    :121sup, after 120sup, 6M
    12.1 Security   :done, after 121sup, 6M
```

_Dates are subject to change. Visit the [schedule](https://www.drupal.org/about/core/policies/core-release-cycles/schedule) page for the most up to date information._

---

### Export graph as image

- Copy the mermaid markdown code
  - If you are viewing the file via the GitLab UI, use the `Copy to Clipboard` button.
  - If you are editing this file, copy the mermaid markdown (do not include the wrapping backticks).

 - Go to the [Mermaid Live](https://mermaid.live) editor
   - Paste the code you just copied.
   - Find the `Actions` dropdown, then select the desired format button to download an export of the graph.
