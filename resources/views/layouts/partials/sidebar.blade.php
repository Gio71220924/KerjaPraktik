<nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
    <div class="sb-sidenav-menu">
        <div class="nav">
            {{-- <a class="nav-link" href="{{ url('dashboard') }}">
                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                Dashboard
            </a> --}}

            <div class="sb-sidenav-menu-heading">Menu</div>
            <a class="nav-link" href="{{ url('project') }}">
                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                Project
            </a>
            
            <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseAttribute" aria-expanded="false" aria-controls="collapseAttribute">
                <div class="sb-nav-link-icon"><i class="fas fa-columns"></i></div>
                Attribute Menu
                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
            </a>
            <div class="collapse" id="collapseAttribute" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                <nav class="sb-sidenav-menu-nested nav">
                    <a class="nav-link" href="{{ url('attributte') }}">Attribute</a>
                    <a class="nav-link" href="{{ url('attributteValue') }}">Attribute Value</a>
                </nav>
            </div>

            <a class="nav-link" href="{{ url('generateCase') }}">
                    <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                    Generate Case
                </a>

            <a class="nav-link" href="{{ url('tree') }}">
                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                Decision Tree
            </a>

            <a class="nav-link" href="{{ url('rule') }}">
                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                Rule
            </a>

            <a class="nav-link" href="{{ url('consultation') }}">
                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                Consultation
            </a>

            <a class="nav-link" href="{{ url('inference') }}">
                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                History
            </a>
{{-- 
            <a class="nav-link" href="{{ url('forwardChaining') }}">
                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                Forward Chaining
            </a> --}}
        </div>
    </div>
</nav>