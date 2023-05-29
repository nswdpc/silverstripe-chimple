<div class="form">
    <% if $Code %>
        {$ChimpleSubscribeForm($Code)}
    <% else %>
        {$ChimpleGlobalSubscribeForm}
    <% end_if %>
</div>
