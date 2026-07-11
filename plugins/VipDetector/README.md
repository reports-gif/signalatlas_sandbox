# VipDetector

## Description

This plugin links the IP of the visitor with a database of IP ranges to be able to recognize special visitors.
The IP ranges can be imported from a json file, either via a command line command or using the scheduler to download it.
For more infos check the docs and the FAQ.
The minimum required PHP version is 7.4. Recommended is 8.0+. Starting with v3.0.0, PHP 7.3 is no longer supported.

### Warning

At the moment it is not possible to remove ranges from the database without manual database changes.
As a workaround you can uninstall the plugin (this deletes the tables) and install the plugin again.

## About

Developed by [Sebastian Elisa Pfeifer](https://blog.sebastian-elisa-pfeifer.eu/) with limited knowledge of what I'm doing.
