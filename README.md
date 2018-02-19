# WP Post Relationships

A schema for creating post -> child post relationships. *Requires Advanced Custom Fields*.

To use just drop in to either `wp-content/plugins` or `wp-content/mu-plugins`.

## Filters
`wp_post_relationship_fields` lets you change or add to the fields under the Post Relationships meta box.

`wp_post_relationship_locations` lets you specify the location logic for where the post relationships fields appear. Defaults to just `post`.

`wp_post_relationship_set_children` lets you modify the fields being sent to `wp_update_post()`. By default all that is passed is the ID of the post being acted upon ( a child post ) and the parent post ID.

`wp_post_relationship_unset_children` same as above but used when removing children -> parent relationships from a child post.

## Actions
`wp_post_relationship_set_children` fires immediately before `wp_update_post()` has an array that passes the following information through:
- Parent Post ID
- Current Post ID (Child post being acted upon)
- Taxonomies (an array of all taxonomies for the post)

`wp_post_relationship_unset_children` fires immediately before `wp_update_post()` when removing children -> parent post relationship on a child post. It has an array the ID of the post being acted on.
