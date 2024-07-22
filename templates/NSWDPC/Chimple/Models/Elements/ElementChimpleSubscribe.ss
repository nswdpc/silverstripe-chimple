<% if $SubscribeForm %>
    <% include ChimpleSubscribeTitle %>
    <div class="element element-content<% if $StyleVariant %> $StyleVariant<% end_if %>">

        <% if $BeforeFormContent %>
            <div class="content pre">
                {$BeforeFormContent}
            </div>
        <% end_if %>

        <% if $Image %>
            <div class="image">
                {$Image}
            </div>
        <% end_if %>

        <div class="form">
            {$SubscribeForm}
        </div>

        <% if $AfterFormContent %>
            <div class="content post">
                {$AfterFormContent}
            </div>
        <% end_if %>

    </div>
<% end_if %>
