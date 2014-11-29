# Piwik counter plugin

## Description

Display Hits/Visits on image. Display Hits/Visits/from Countries stats as text via ajax requests.

## FAQ

See http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html

## Changelog
2.0.9
* Fix for https://github.com/Globulopolis/Counter/issues/5

2.0.8
* Fix for https://github.com/Globulopolis/Counter/issues/4#issuecomment-59620132

2.0.7
* Add 'yesterday' option for "Start date - period".

2.0.6
* Fixed an error when user select image with type different from png or gif. Now plugin support jpg image type.
* Update colorpicker to latest.

2.0.5
* Fixed a bug w/ undefined variable 'userMenu' in '@CoreHome/_topBarTopMenu.twig' on new Piwik 2.4.0

2.0.4
* Added custom offsets for visits/views/countries for 'visitors by countries' template.

2.0.3
* Added custom template for 'visitors by countries'. NB! 'Live visitors counter' works only if custom template field for 'Visitors by countries' is empty.
* Fixed an error w/ undefined method Access::isSuperUser
* Fix for double slash in ajax url
* Added workaround for getallheaders() method if PHP running as CGI.
* Remove PIWIK_ENABLE_DISPATCH due to triggering an error while generating counter image.

2.0.2
* Fixed a bug where the URL with the image displayed via http, if you are using https(bug only in counters list).

2.0.1
* Fix for CORS (thanks for aureq for patch)
* Changing versioning according to requirements

2.0 Initial release

## Support

http://xn--80aeqbhthr9b.com/en/contact-form.html