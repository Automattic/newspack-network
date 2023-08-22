# Testing

Here are a few scenarios to make sure the plugin is working as expected.

## Basic communication

* Follow the "Setting up" steps on [the readme file](README.md) to configure the Hub and at least two Nodes.
* Make sure RAS is enabled in all sites
* As a reader, register to one of the Nodes
* Wait a couple of minutes for the async actions to run
* Visit the Event Log panel on the Hub and confirm the user registration event on the Node is there
* Still on the Hub, check your Users list and confirm the user email registered on the Node was created as a Network Reader in the Hub
* On the second Node, check that the reader was also created (it might take another couple of minutes)

See the troubleshooting section in the [developer docs](DEV_NOTES.md) to learn how to follow the events.

## Specific events

`reader_registered` is covered in the instructions above

### `newspack_node_order_changed` and `newspack_node_subscription_changed`: 

* In one of the Nodes, make a Woocommerce purchase.
* Check that the event show up in the Hub's Event Log
* In the Hub, go to the Subscriptions or Order panels under the "Newspack Network" menu and confirm you see the Order or Subscription there, and that its links point to the Node
* Back to the Node admin, make a change to the Order or Subscription (for example changing its status)
* Confirm the change shows up in the Event Log
* Confirm that the Order or Subscription in the panel shows the updated status