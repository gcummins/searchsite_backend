This directory contains files that manage the mapping of specific feeds into a generic format.

I chose to use a generic format to avoid the need for feed-specific code on the front-end. This also makes Sphinx
indexing quicker and more thorough. No information is lost when the mapping takes place. I have created enough fields
in the generic format that all of the data has a place. The front-end code does a little bit of picking-and-choosing
based on which fields actually contain data.