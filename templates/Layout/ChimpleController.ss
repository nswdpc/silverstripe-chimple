
<h1>$Title</h1>

$Breadcrumbs

<section role="main">

    <% if $IsComplete %>
        <p>Your subscription was successful</p>
    <% else %>
        <p>Your subscription was not successful</p>
        <% include ChimpleSubscribeForm Code=$Code %>
    <% end_if %>

</div>
