# FAQ

## Why

I am a let's say "known person" in the Austrian government, mostly because I was one of five people suing them in a civil rights case.
And I've a blog. Said blog gets visited by government agencies regularly, and I wanted to have an overview of this.

## Limitations

The subnets you want to match on can't be smaller than what you set as the anonymization factor for the IP addresses.
For example if you set the masking for 2 bytes as recommended, the smallest subnet you can match is a /16, if you mask the last byte it is /24.
At the moment it is not possible to remove ranges from the database without manual database changes.
As a workaround you can uninstall the plugin (this deletes the tables) and install the plugin again.
