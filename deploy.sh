#!/bin/sh

# change cwd to script location
cd "$(dirname "$0")"

# compress archive
tar cfz aws-marketplace-oe-patterns-drupal-example-site.tar.gz \
    --exclude="sites/default/settings.local.php" \
    drupal

if aws s3 cp aws-marketplace-oe-patterns-drupal-example-site.tar.gz \
       s3://github-user-and-bucket-githubartifactbucket-1c9jk3sjkqv8p/aws-marketplace-oe-patterns-drupal-example-site.tar.gz ; then
    rm aws-marketplace-oe-patterns-drupal-example-site.tar.gz
else
    print "AWS deployment failed -- are you running with aws-vault and oe-patterns-dev-github credentials?"
fi

